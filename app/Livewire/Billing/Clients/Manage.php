<?php

namespace App\Livewire\Billing\Clients;

use App\Models\BillingTaxSetting;
use App\Models\Client;
use Flux\Flux;
use Livewire\Component;

class Manage extends Component
{
    public ?Client $client = null;

    public string $name = '';

    public string $company_name = '';

    public string $email = '';

    public string $address_line = '';

    public string $postal_code = '';

    public string $city = '';

    public string $country = '';

    public string $phone = '';

    public string $vat_number = '';

    public bool $vat_exception = false;

    public bool $irs_exception = false;

    public string $currency = 'EUR';

    public bool $is_active = true;

    public ?string $registered_at = null;

    public string $notes = '';

    public function mount(?Client $client = null): void
    {
        $this->client = $client;
        if ($client) {
            $data = $client->only([
                'name', 'company_name', 'email', 'address_line', 'postal_code', 'city', 'country',
                'phone', 'vat_number', 'vat_exception', 'irs_exception', 'currency', 'is_active', 'registered_at', 'notes',
            ]);
            $this->fill(collect($data)->map(function ($value, string $key) {
                if ($key === 'is_active') {
                    return (bool) $value;
                }
                if ($key === 'registered_at') {
                    return $value?->format('Y-m-d') ?? '';
                }

                return $value ?? '';
            })->all());
        } else {
            $this->registered_at = now()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:64'],
            'vat_number' => ['nullable', 'string', 'max:64'],
            'vat_exception' => ['boolean'],
            'irs_exception' => ['boolean'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'registered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
        if ($this->client) {
            $rules['email'][] = 'unique:clients,email,'.$this->client->id;
        } else {
            $rules['email'][] = 'unique:clients,email';
        }
        $data = $this->validate($rules);
        if (($data['registered_at'] ?? '') === '') {
            $data['registered_at'] = null;
        }
        if ($this->client) {
            $this->client->update($data);
            Flux::toast(variant: 'success', text: __('Client updated.'));
            $this->redirect(route('billing.clients.show', $this->client), navigate: true);

            return;
        }
        $client = Client::query()->create($data);
        Flux::toast(variant: 'success', text: __('Client created.'));
        $this->redirect(route('billing.clients.show', $client), navigate: true);
    }

    public function render()
    {
        $title = $this->client ? __('Edit client') : __('New client');

        $taxSettings = BillingTaxSetting::instance();

        return view('livewire.billing.clients.manage', compact('title', 'taxSettings'))->title($title);
    }
}
