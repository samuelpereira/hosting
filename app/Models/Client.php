<?php

namespace App\Models;

use App\Enums\ContactRole;
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
            'company_name' => 'encrypted',
            'address_line' => 'encrypted',
            'postal_code' => 'encrypted',
            'city' => 'encrypted',
            'country' => 'encrypted',
            'phone' => 'encrypted',
            'vat_number' => 'encrypted',
            'notes' => 'encrypted',
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

    /** Contact roles that receive billing emails (CC); technical/other contacts do not. */
    public const BILLING_EMAIL_CONTACT_ROLES = [ContactRole::Owner, ContactRole::Billing];

    /**
     * Unique billing email addresses: client first, then Owner/Billing contacts
     * (case-insensitive dedupe). The first address is used as "To", the rest as CC
     * (see BillingEmailSender::resolveDeliveryAddresses()).
     *
     * @return list<string>
     */
    public function billingEmailRecipients(): array
    {
        $emails = [];

        $clientEmail = strtolower(trim((string) ($this->email ?? '')));
        if ($clientEmail !== '') {
            $emails[] = $clientEmail;
        }

        $contacts = $this->relationLoaded('contacts')
            ? $this->contacts->filter(fn (Contact $contact) => in_array($contact->role, self::BILLING_EMAIL_CONTACT_ROLES, true))
            : $this->contacts()->whereIn('role', self::BILLING_EMAIL_CONTACT_ROLES)->get(['email']);

        foreach ($contacts as $contact) {
            $contactEmail = strtolower(trim((string) $contact->email));
            if ($contactEmail !== '' && ! in_array($contactEmail, $emails, true)) {
                $emails[] = $contactEmail;
            }
        }

        return $emails;
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
