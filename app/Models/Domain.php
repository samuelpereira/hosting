<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Enums\RegistrarTldPriceOperation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $fillable = [
        'whmcs_id', 'client_id', 'registrar_id', 'registrar_tld_id', 'registrar_tld_price_id', 'fqdn',
        'status', 'currency', 'first_payment_amount_minor', 'recurring_amount_minor',
        'interval_unit', 'interval_count',
        'registered_at', 'expires_at', 'due_at',
        'auto_renew', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'interval_unit' => IntervalUnit::class,
            'interval_count' => 'integer',
            'registered_at' => 'date',
            'expires_at' => 'date',
            'due_at' => 'date',
            'auto_renew' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }

    public function registrarTld(): BelongsTo
    {
        return $this->belongsTo(RegistrarTld::class);
    }

    public function registrarTldPrice(): BelongsTo
    {
        return $this->belongsTo(RegistrarTldPrice::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function isEligibleForAutomatedRenewal(): bool
    {
        return $this->auto_renew && $this->status->isEligibleForRenewalOrder();
    }

    /** Renewal price normalized to a monthly amount (minor units). */
    public function monthlyRevenueMinor(): int
    {
        return $this->normalizeAmountToMonthlyMinor($this->recurring_amount_minor);
    }

    /** Renewal price normalized to a yearly amount (minor units). */
    public function yearlyRevenueMinor(): int
    {
        return $this->normalizeAmountToYearlyMinor($this->recurring_amount_minor);
    }

    /** Registrar cost from the linked TLD price (or matching renew price), monthly (minor units). */
    public function monthlyCostMinor(): int
    {
        $price = $this->registrarTldPrice ?? $this->resolveRenewPriceForCost();

        return $price?->monthlyDomainCostMinor() ?? 0;
    }

    /** Registrar cost normalized to a yearly amount (minor units). */
    public function yearlyCostMinor(): int
    {
        $price = $this->registrarTldPrice ?? $this->resolveRenewPriceForCost();
        if ($price === null || $price->domain_cost_minor === null) {
            return 0;
        }

        $termYears = max(1, (int) $price->term_years);

        return (int) round((int) $price->domain_cost_minor / $termYears);
    }

    private function resolveRenewPriceForCost(): ?RegistrarTldPrice
    {
        if ($this->registrar_tld_id === null) {
            return null;
        }

        $termYears = ($this->interval_unit ?? IntervalUnit::Year) === IntervalUnit::Year
            ? max(1, (int) $this->interval_count)
            : 1;

        return RegistrarTldPrice::query()
            ->where('registrar_tld_id', $this->registrar_tld_id)
            ->where('operation', RegistrarTldPriceOperation::Renew)
            ->where('is_active', true)
            ->where('term_years', $termYears)
            ->first()
            ?? RegistrarTldPrice::query()
                ->where('registrar_tld_id', $this->registrar_tld_id)
                ->where('operation', RegistrarTldPriceOperation::Renew)
                ->where('is_active', true)
                ->orderBy('term_years')
                ->first();
    }

    private function normalizeAmountToMonthlyMinor(?int $amountMinor): int
    {
        if ($amountMinor === null) {
            return 0;
        }

        $months = match ($this->interval_unit ?? IntervalUnit::Year) {
            IntervalUnit::Month => max(1, (int) $this->interval_count),
            IntervalUnit::Year => max(1, (int) $this->interval_count) * 12,
        };

        return (int) round((int) $amountMinor / $months);
    }

    private function normalizeAmountToYearlyMinor(?int $amountMinor): int
    {
        if ($amountMinor === null) {
            return 0;
        }

        return match ($this->interval_unit ?? IntervalUnit::Year) {
            IntervalUnit::Month => (int) round((int) $amountMinor * 12 / max(1, (int) $this->interval_count)),
            IntervalUnit::Year => (int) round((int) $amountMinor / max(1, (int) $this->interval_count)),
        };
    }

    public function advanceByBillingInterval(CarbonInterface $date): Carbon
    {
        $start = Carbon::instance($date)->startOfDay();
        $count = max(1, (int) $this->interval_count);
        $unit = $this->interval_unit ?? IntervalUnit::Year;

        return match ($unit) {
            IntervalUnit::Month => $start->copy()->addMonths($count),
            IntervalUnit::Year => $start->copy()->addYears($count),
        };
    }

    public function renewalOrderLineDescription(?CarbonInterface $periodStart = null): string
    {
        $start = $periodStart !== null
            ? Carbon::instance($periodStart)->startOfDay()
            : ($this->due_at?->copy()->startOfDay());

        if ($start === null) {
            return sprintf('Renovação de Domínio - %s', $this->fqdn);
        }

        $end = $this->advanceByBillingInterval($start);

        return sprintf(
            'Renovação de Domínio - %s (%s - %s)',
            $this->fqdn,
            $start->format('d/m/Y'),
            $end->format('d/m/Y'),
        );
    }

    /**
     * @return array{0: IntervalUnit, 1: int}
     */
    public static function billingIntervalFromRegistrarTld(
        ?RegistrarTld $tld,
        ?RegistrarTldPrice $price = null,
    ): array {
        if ($price !== null) {
            return $price->billingInterval();
        }

        if ($tld !== null) {
            return $tld->defaultBillingInterval(RegistrarTldPriceOperation::Renew);
        }

        return [IntervalUnit::Year, 1];
    }
}
