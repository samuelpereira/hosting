<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'whmcs_id', 'name', 'company_name', 'email', 'address_line', 'postal_code', 'city', 'country',
        'phone', 'vat_number', 'vat_exception', 'irs_exception', 'currency', 'is_active', 'registered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'vat_exception' => 'boolean',
            'irs_exception' => 'boolean',
            'registered_at' => 'datetime',
        ];
    }

    public function effectiveVatRatePercent(): float
    {
        return BillingTaxSetting::instance()->vatRateForClient($this);
    }

    public function effectiveIrsRatePercent(): float
    {
        return BillingTaxSetting::instance()->irsRateForClient($this);
    }

    /** First and last token of name (e.g. "John Michael Smith" → "John Smith"). */
    public function firstAndLastName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return '';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.$parts[array_key_last($parts)];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ServiceStatus::Active->value])
            ->orderByDesc('id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [DomainStatus::Active->value])
            ->orderByDesc('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderByDesc('id');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(ClientEmailLog::class)->orderByDesc('sent_at')->orderByDesc('id');
    }
}
