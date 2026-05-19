<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCompanySetting extends Model
{
    protected $fillable = [
        'name',
        'address_line',
        'postal_code',
        'city',
        'country',
        'phone',
        'email',
        'bank_account',
    ];

    public static function instance(): self
    {
        $defaults = config('billing.company', []);

        return static::query()->firstOrCreate(
            [],
            [
                'name' => $defaults['name'] ?? null,
                'address_line' => $defaults['address_line'] ?? null,
                'postal_code' => $defaults['postal_code'] ?? null,
                'city' => $defaults['city'] ?? null,
                'country' => $defaults['country'] ?? null,
                'phone' => $defaults['phone'] ?? null,
                'email' => $defaults['email'] ?? null,
                'bank_account' => $defaults['bank_account'] ?? null,
            ],
        );
    }

    public function displayName(): string
    {
        $name = trim((string) ($this->name ?? ''));

        return $name !== '' ? $name : (string) config('app.name');
    }

    /**
     * Placeholders for billing email templates.
     *
     * @return array<string, string>
     */
    public function emailVariables(): array
    {
        return [
            'company_name' => $this->displayName(),
            'company_address' => trim((string) ($this->address_line ?? '')),
            'company_postal_code' => trim((string) ($this->postal_code ?? '')),
            'company_city' => trim((string) ($this->city ?? '')),
            'company_country' => trim((string) ($this->country ?? '')),
            'company_phone' => trim((string) ($this->phone ?? '')),
            'company_email' => trim((string) ($this->email ?? '')),
            'company_bank_account' => trim((string) ($this->bank_account ?? '')),
        ];
    }
}
