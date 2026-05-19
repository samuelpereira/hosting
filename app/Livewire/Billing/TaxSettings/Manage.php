<?php

namespace App\Livewire\Billing\TaxSettings;

use App\Models\BillingTaxSetting;
use Flux\Flux;
use Livewire\Component;

class Manage extends Component
{
    public string $vat_rate_input = '23.00';

    public string $irs_rate_input = '25.00';

    public bool $domains_irs_exception = false;

    public function mount(): void
    {
        $settings = BillingTaxSetting::instance();

        $this->vat_rate_input = number_format((float) $settings->vat_rate_percent, 2, '.', '');
        $this->irs_rate_input = number_format((float) $settings->irs_rate_percent, 2, '.', '');
        $this->domains_irs_exception = $settings->domains_irs_exception;
    }

    public function save(): void
    {
        $this->validate([
            'vat_rate_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'numeric', 'min:0', 'max:100'],
            'irs_rate_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'numeric', 'min:0', 'max:100'],
            'domains_irs_exception' => ['boolean'],
        ]);

        $settings = BillingTaxSetting::instance();
        $settings->update([
            'vat_rate_percent' => round((float) $this->vat_rate_input, 2),
            'irs_rate_percent' => round((float) $this->irs_rate_input, 2),
            'domains_irs_exception' => $this->domains_irs_exception,
        ]);

        Flux::toast(variant: 'success', text: __('Tax settings saved.'));
    }

    public function render()
    {
        return view('livewire.billing.tax-settings.manage')
            ->title(__('Tax settings'));
    }
}
