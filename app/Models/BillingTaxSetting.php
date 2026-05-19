<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingTaxSetting extends Model
{
    protected $fillable = [
        'vat_rate_percent',
        'irs_rate_percent',
        'domains_irs_exception',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate_percent' => 'decimal:2',
            'irs_rate_percent' => 'decimal:2',
            'domains_irs_exception' => 'boolean',
        ];
    }

    public static function instance(): self
    {
        return static::query()->firstOrCreate(
            [],
            [
                'vat_rate_percent' => config('billing.default_vat_rate_percent', 23),
                'irs_rate_percent' => config('billing.default_irs_rate_percent', 25),
                'domains_irs_exception' => config('billing.domains_irs_exception', false),
            ],
        );
    }

    public function vatRateForClient(Client $client): float
    {
        return $client->vat_exception ? 0.0 : (float) $this->vat_rate_percent;
    }

    public function irsRateForClient(Client $client, bool $isDomainLine = false): float
    {
        if ($client->irs_exception || ($isDomainLine && $this->domains_irs_exception)) {
            return 0.0;
        }

        return (float) $this->irs_rate_percent;
    }

    public function vatMinorForLine(Client $client, int $lineSubtotalMinor): int
    {
        if ($lineSubtotalMinor <= 0) {
            return 0;
        }

        return (int) round($lineSubtotalMinor * $this->vatRateForClient($client) / 100);
    }

    public function irsMinorForLine(Client $client, int $lineSubtotalMinor, bool $isDomainLine = false): int
    {
        if ($lineSubtotalMinor <= 0) {
            return 0;
        }

        return (int) round($lineSubtotalMinor * $this->irsRateForClient($client, $isDomainLine) / 100);
    }
}
