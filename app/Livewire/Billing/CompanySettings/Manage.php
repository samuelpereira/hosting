<?php

namespace App\Livewire\Billing\CompanySettings;

use App\Models\BillingCompanySetting;
use Flux\Flux;
use Livewire\Component;

class Manage extends Component
{
    public string $name = '';

    public string $address_line = '';

    public string $postal_code = '';

    public string $city = '';

    public string $country = '';

    public string $phone = '';

    public string $email = '';

    public string $bank_account = '';

    public function mount(): void
    {
        $settings = BillingCompanySetting::instance();

        $this->name = (string) ($settings->name ?? '');
        $this->address_line = (string) ($settings->address_line ?? '');
        $this->postal_code = (string) ($settings->postal_code ?? '');
        $this->city = (string) ($settings->city ?? '');
        $this->country = (string) ($settings->country ?? '');
        $this->phone = (string) ($settings->phone ?? '');
        $this->email = (string) ($settings->email ?? '');
        $this->bank_account = (string) ($settings->bank_account ?? '');
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:2'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:2000'],
        ]);

        BillingCompanySetting::instance()->update([
            'name' => $this->name !== '' ? $this->name : null,
            'address_line' => $this->address_line !== '' ? $this->address_line : null,
            'postal_code' => $this->postal_code !== '' ? $this->postal_code : null,
            'city' => $this->city !== '' ? $this->city : null,
            'country' => $this->country !== '' ? strtoupper($this->country) : null,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'email' => $this->email !== '' ? $this->email : null,
            'bank_account' => $this->bank_account !== '' ? $this->bank_account : null,
        ]);

        Flux::toast(variant: 'success', text: __('Company details saved.'));
    }

    public function render()
    {
        return view('livewire.billing.company-settings.manage')
            ->title(__('Company details'));
    }
}
