<?php

namespace App\Services\Billing;

use App\Models\Domain;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Carbon;

class OrderLinkedAssetRenewal
{
    public function __construct(
        private readonly Order $order,
    ) {}

    public function apply(): void
    {
        $this->order->loadMissing(['items.service.domain', 'items.domain']);

        /** @var array<int, true> $updatedDomainIds */
        $updatedDomainIds = [];

        $serviceIds = $this->order->items
            ->pluck('service_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($serviceIds as $serviceId) {
            $service = $this->order->items->firstWhere('service_id', $serviceId)?->service
                ?? Service::query()->find($serviceId);

            if (! $service instanceof Service) {
                continue;
            }

            $newDue = $this->advanceDueAt($service);
            $service->update(['due_at' => $newDue]);

            if ($service->domain_id) {
                $domain = $service->relationLoaded('domain')
                    ? $service->domain
                    : Domain::query()->find($service->domain_id);

                $this->renewDomainFromService($domain, $newDue, $updatedDomainIds);
            }
        }

        $domainIds = $this->order->items
            ->pluck('domain_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($domainIds as $domainId) {
            if (isset($updatedDomainIds[$domainId])) {
                continue;
            }

            $domain = $this->order->items->firstWhere('domain_id', $domainId)?->domain
                ?? Domain::query()->find($domainId);

            if (! $domain instanceof Domain) {
                continue;
            }

            $domain->update([
                'due_at' => $this->advanceDueAt($domain)->toDateString(),
                'expires_at' => $this->advanceExpiresAt($domain)->toDateString(),
            ]);

            $updatedDomainIds[$domainId] = true;
        }
    }

    private function renewDomainFromService(?Domain $domain, Carbon $serviceDueAt, array &$updatedDomainIds): void
    {
        if (! $domain instanceof Domain) {
            return;
        }

        $domain->update([
            'due_at' => $serviceDueAt->toDateString(),
            'expires_at' => $this->advanceExpiresAt($domain)->toDateString(),
        ]);

        $updatedDomainIds[$domain->id] = true;
    }

    private function renewalStart(Service|Domain $model): Carbon
    {
        if ($model->due_at) {
            return Carbon::parse($model->due_at)->startOfDay();
        }

        return now()->startOfDay();
    }

    private function advanceDueAt(Service|Domain $model): Carbon
    {
        return $model->advanceByBillingInterval($this->renewalStart($model));
    }

    private function advanceExpiresAt(Domain $domain): Carbon
    {
        $start = $domain->expires_at
            ? Carbon::parse($domain->expires_at)->startOfDay()
            : $this->renewalStart($domain);

        return $domain->advanceByBillingInterval($start);
    }
}
