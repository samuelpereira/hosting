<?php

namespace App\Livewire\Billing\AutomationSettings;

use App\Models\BillingAutomationSetting;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Component;

class Manage extends Component
{
    public bool $is_enabled = true;

    public string $run_at_time = '09:00';

    public int $service_order_days_before = 14;

    public int $domain_order_days_before = 30;

    public int $reminder_days_before_first = 7;

    public int $reminder_days_before_due = 0;

    public int $reminder_days_after_due = 14;

    public bool $send_order_created_email = true;

    public bool $send_reminder_emails = true;

    public function mount(): void
    {
        $settings = BillingAutomationSetting::instance();

        $this->is_enabled = $settings->is_enabled;
        $this->run_at_time = Carbon::parse($settings->run_at_time)->format('H:i');
        $this->service_order_days_before = $settings->service_order_days_before;
        $this->domain_order_days_before = $settings->domain_order_days_before;
        $this->reminder_days_before_first = $settings->reminder_days_before_first;
        $this->reminder_days_before_due = $settings->reminder_days_before_due;
        $this->reminder_days_after_due = $settings->reminder_days_after_due;
        $this->send_order_created_email = $settings->send_order_created_email;
        $this->send_reminder_emails = $settings->send_reminder_emails;
    }

    public function save(): void
    {
        $this->validate([
            'is_enabled' => ['boolean'],
            'run_at_time' => ['required', 'date_format:H:i'],
            'service_order_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'domain_order_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'reminder_days_before_first' => ['required', 'integer', 'min:0', 'max:365'],
            'reminder_days_before_due' => ['required', 'integer', 'min:0', 'max:365'],
            'reminder_days_after_due' => ['required', 'integer', 'min:0', 'max:365'],
            'send_order_created_email' => ['boolean'],
            'send_reminder_emails' => ['boolean'],
        ]);

        BillingAutomationSetting::instance()->update([
            'is_enabled' => $this->is_enabled,
            'run_at_time' => $this->run_at_time.':00',
            'service_order_days_before' => $this->service_order_days_before,
            'domain_order_days_before' => $this->domain_order_days_before,
            'reminder_days_before_first' => $this->reminder_days_before_first,
            'reminder_days_before_due' => $this->reminder_days_before_due,
            'reminder_days_after_due' => $this->reminder_days_after_due,
            'send_order_created_email' => $this->send_order_created_email,
            'send_reminder_emails' => $this->send_reminder_emails,
        ]);

        Flux::toast(variant: 'success', text: __('Automation settings saved.'));
    }

    public function render()
    {
        return view('livewire.billing.automation-settings.manage')
            ->title(__('Automation'));
    }
}
