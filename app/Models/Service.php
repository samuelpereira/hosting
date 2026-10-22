<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\ServiceStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Service extends Model
{
    protected $fillable = [
        'whmcs_id', 'client_id', 'product_id', 'username', 'password', 'currency',
        'first_payment_amount_minor', 'recurring_amount_minor', 'interval_unit', 'interval_count',
        'server_id', 'domain_id', 'associated_domain',
        'status', 'activated_at', 'due_at',
        'cancelled_at', 'allocated_ram_gb', 'allocated_disk_gb', 'notes',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            'interval_unit' => IntervalUnit::class,
            'interval_count' => 'integer',
            'password' => 'encrypted',
            'activated_at' => 'datetime',
            'due_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Placeholders for the service_welcome email template.
     *
     * @return array<string, string>
     */
    /** Recurring price normalized to a monthly amount (minor units). */
    public function monthlyAmountMinor(): int
    {
        if ($this->recurring_amount_minor === null) {
            return 0;
        }

        $months = match ($this->interval_unit ?? IntervalUnit::Month) {
            IntervalUnit::Month => max(1, (int) $this->interval_count),
            IntervalUnit::Year => max(1, (int) $this->interval_count) * 12,
        };

        return (int) round((int) $this->recurring_amount_minor / $months);
    }

    /** Recurring price normalized to a yearly amount (minor units). */
    public function yearlyAmountMinor(): int
    {
        return $this->monthlyAmountMinor() * 12;
    }

    public function billingIntervalLabel(): string
    {
        $count = max(1, (int) $this->interval_count);
        $unit = $this->interval_unit ?? IntervalUnit::Month;

        return match ($unit) {
            IntervalUnit::Month => $count === 1
                ? __('Monthly')
                : __('Every :count months', ['count' => $count]),
            IntervalUnit::Year => $count === 1
                ? __('Yearly')
                : __('Every :count years', ['count' => $count]),
        };
    }

    public function advanceByBillingInterval(CarbonInterface $date): Carbon
    {
        $start = Carbon::instance($date)->startOfDay();
        $count = max(1, (int) $this->interval_count);
        $unit = $this->interval_unit ?? IntervalUnit::Month;

        return match ($unit) {
            IntervalUnit::Month => $start->copy()->addMonths($count),
            IntervalUnit::Year => $start->copy()->addYears($count),
        };
    }

    public function renewalBillableAmountMinor(): int
    {
        return (int) ($this->recurring_amount_minor ?? $this->first_payment_amount_minor ?? 0);
    }

    public function isBillableForRenewal(): bool
    {
        return $this->renewalBillableAmountMinor() > 0;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBillableForRenewal($query)
    {
        return $query->whereRaw('COALESCE(recurring_amount_minor, first_payment_amount_minor, 0) > 0');
    }

    public function renewalOrderLineDescription(?CarbonInterface $periodStart = null): string
    {
        $this->loadMissing(['product', 'domain']);

        $serviceName = (string) ($this->product?->name ?? __('Service'));
        $domainName = trim((string) ($this->associated_domain ?? $this->domain?->fqdn ?? ''));

        $start = $periodStart !== null
            ? Carbon::instance($periodStart)->startOfDay()
            : ($this->due_at?->copy()->startOfDay());

        if ($start === null) {
            return $domainName !== ''
                ? sprintf('%s - %s', $serviceName, $domainName)
                : $serviceName;
        }

        $end = $this->advanceByBillingInterval($start);
        $period = sprintf('%s - %s', $start->format('d/m/Y'), $end->format('d/m/Y'));

        return $domainName !== ''
            ? sprintf('%s - %s (%s)', $serviceName, $domainName, $period)
            : sprintf('%s (%s)', $serviceName, $period);
    }

    public function welcomeEmailVariables(): array
    {
        $this->loadMissing(['client', 'product', 'server']);

        return array_merge(BillingCompanySetting::instance()->emailVariables(), [
            'client_name' => (string) ($this->client?->name ?? ''),
            'product_name' => (string) ($this->product?->name ?? ''),
            'domain' => (string) ($this->associated_domain ?? $this->domain?->fqdn ?? ''),
            'service_id' => (string) $this->id,
            'username' => (string) ($this->username ?? ''),
            'password' => (string) ($this->password ?? ''),
            'panel_url' => (string) ($this->server?->panel_url ?? ''),
        ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Orders that include at least one line item linked to this service. */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items', 'service_id', 'order_id')
            ->distinct();
    }
}
