<?php

namespace App\Services\Billing;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\ServiceStatus;
use App\Models\BillingAutomationLog;
use App\Models\BillingTaxSetting;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RenewalOrderFactory
{
    public function __construct(
        private readonly OrderTotalsCalculator $totalsCalculator,
    ) {}

    public function createForService(Service $service, CarbonInterface $renewalDueDate): ?Order
    {
        $service->loadMissing(['client', 'product', 'domain']);

        if ($service->status !== ServiceStatus::Active) {
            return null;
        }

        $client = $service->client;
        if ($client === null) {
            return null;
        }

        if (BillingAutomationLog::wasHandled(
            BillingAutomationLog::SUBJECT_SERVICE,
            $service->id,
            BillingAutomationLog::EVENT_SERVICE_RENEWAL_ORDER,
            $renewalDueDate,
        )) {
            return null;
        }

        if ($this->hasOpenRenewalOrderForService($service, $renewalDueDate)) {
            return null;
        }

        $lines = $this->buildServiceLines($service, $renewalDueDate);
        if ($lines === []) {
            return null;
        }

        return DB::transaction(function () use ($service, $client, $renewalDueDate, $lines) {
            $totals = $this->totalsCalculator->forClientLines($client, $lines);
            $currency = strtoupper($service->currency ?: $client->currency ?: 'EUR');

            $order = Order::query()->create([
                'client_id' => $client->id,
                'number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Unpaid,
                'currency' => $currency,
                'subtotal_minor' => $totals['subtotal_minor'],
                'tax_minor' => $totals['tax_minor'],
                'irs_minor' => $totals['irs_minor'],
                'total_minor' => $totals['total_minor'],
                'issued_at' => now(),
                'due_at' => Carbon::instance($renewalDueDate)->startOfDay(),
                'notes' => __('Auto-renewal for service #:id', ['id' => $service->id]),
            ]);

            foreach ($lines as $position => $line) {
                $order->items()->create([
                    'position' => $position,
                    'type' => $line['type'],
                    'description' => $line['description'],
                    'quantity' => 1,
                    'unit_amount_minor' => $line['line_subtotal_minor'],
                    'tax_amount_minor' => $this->vatMinorForLine($client, $line['line_subtotal_minor']),
                    'service_id' => $line['service_id'] ?? null,
                    'domain_id' => $line['domain_id'] ?? null,
                ]);
            }

            BillingAutomationLog::record(
                BillingAutomationLog::SUBJECT_SERVICE,
                $service->id,
                BillingAutomationLog::EVENT_SERVICE_RENEWAL_ORDER,
                $renewalDueDate,
                $order->id,
            );

            return $order;
        });
    }

    public function createForDomain(Domain $domain, CarbonInterface $renewalDueDate): ?Order
    {
        $domain->loadMissing('client');

        $client = $domain->client;
        if ($client === null) {
            return null;
        }

        if (BillingAutomationLog::wasHandled(
            BillingAutomationLog::SUBJECT_DOMAIN,
            $domain->id,
            BillingAutomationLog::EVENT_DOMAIN_RENEWAL_ORDER,
            $renewalDueDate,
        )) {
            return null;
        }

        if ($this->hasOpenRenewalOrderForDomain($domain, $renewalDueDate)) {
            return null;
        }

        $amountMinor = (int) ($domain->recurring_amount_minor ?? $domain->first_payment_amount_minor ?? 0);
        if ($amountMinor <= 0) {
            return null;
        }

        $lines = [[
            'type' => OrderItemType::Domain,
            'description' => $domain->renewalOrderLineDescription($renewalDueDate),
            'line_subtotal_minor' => $amountMinor,
            'domain_id' => $domain->id,
            'is_domain' => true,
        ]];

        return DB::transaction(function () use ($domain, $client, $renewalDueDate, $lines) {
            $totals = $this->totalsCalculator->forClientLines($client, $lines);
            $currency = strtoupper($domain->currency ?: $client->currency ?: 'EUR');

            $order = Order::query()->create([
                'client_id' => $client->id,
                'number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Unpaid,
                'currency' => $currency,
                'subtotal_minor' => $totals['subtotal_minor'],
                'tax_minor' => $totals['tax_minor'],
                'irs_minor' => $totals['irs_minor'],
                'total_minor' => $totals['total_minor'],
                'issued_at' => now(),
                'due_at' => Carbon::instance($renewalDueDate)->startOfDay(),
                'notes' => __('Auto-renewal for domain #:id', ['id' => $domain->id]),
            ]);

            foreach ($lines as $position => $line) {
                $order->items()->create([
                    'position' => $position,
                    'type' => $line['type'],
                    'description' => $line['description'],
                    'quantity' => 1,
                    'unit_amount_minor' => $line['line_subtotal_minor'],
                    'tax_amount_minor' => $this->vatMinorForLine($client, $line['line_subtotal_minor']),
                    'domain_id' => $line['domain_id'],
                ]);
            }

            BillingAutomationLog::record(
                BillingAutomationLog::SUBJECT_DOMAIN,
                $domain->id,
                BillingAutomationLog::EVENT_DOMAIN_RENEWAL_ORDER,
                $renewalDueDate,
                $order->id,
            );

            return $order;
        });
    }

    /**
     * @return list<array{type: OrderItemType, description: string, line_subtotal_minor: int, service_id?: int, domain_id?: int}>
     */
    private function buildServiceLines(Service $service, CarbonInterface $renewalDueDate): array
    {
        $serviceAmountMinor = (int) ($service->recurring_amount_minor ?? $service->first_payment_amount_minor ?? 0);
        if ($serviceAmountMinor <= 0) {
            return [];
        }

        $lines = [[
            'type' => OrderItemType::Service,
            'description' => $service->renewalOrderLineDescription($renewalDueDate),
            'line_subtotal_minor' => $serviceAmountMinor,
            'service_id' => $service->id,
            'is_domain' => false,
        ]];

        if ($service->domain_id && $service->domain) {
            $domain = $service->domain;
            $domainAmountMinor = (int) ($domain->recurring_amount_minor ?? $domain->first_payment_amount_minor ?? 0);
            if ($domainAmountMinor > 0 && $domain->status->isEligibleForRenewalOrder()) {
                $lines[] = [
                    'type' => OrderItemType::Domain,
                    'description' => $domain->renewalOrderLineDescription(),
                    'line_subtotal_minor' => $domainAmountMinor,
                    'domain_id' => $domain->id,
                    'is_domain' => true,
                ];
            }
        }

        return $lines;
    }

    private function hasOpenRenewalOrderForService(Service $service, CarbonInterface $renewalDueDate): bool
    {
        return Order::query()
            ->where('client_id', $service->client_id)
            ->whereIn('status', [OrderStatus::Unpaid, OrderStatus::Draft])
            ->whereDate('due_at', Carbon::instance($renewalDueDate)->toDateString())
            ->whereHas('items', fn ($q) => $q->where('service_id', $service->id))
            ->exists();
    }

    private function hasOpenRenewalOrderForDomain(Domain $domain, CarbonInterface $renewalDueDate): bool
    {
        return Order::query()
            ->where('client_id', $domain->client_id)
            ->whereIn('status', [OrderStatus::Unpaid, OrderStatus::Draft])
            ->whereDate('due_at', Carbon::instance($renewalDueDate)->toDateString())
            ->whereHas('items', fn ($q) => $q->where('domain_id', $domain->id))
            ->exists();
    }

    private function vatMinorForLine(Client $client, int $lineSubtotalMinor): int
    {
        return BillingTaxSetting::instance()->vatMinorForLine($client, $lineSubtotalMinor);
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
