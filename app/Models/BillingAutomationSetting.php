<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BillingAutomationSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'run_at_time',
        'service_order_days_before',
        'domain_order_days_before',
        'reminder_days_before_first',
        'reminder_days_before_due',
        'reminder_days_after_due',
        'send_order_created_email',
        'send_reminder_emails',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'send_order_created_email' => 'boolean',
            'send_reminder_emails' => 'boolean',
            'service_order_days_before' => 'integer',
            'domain_order_days_before' => 'integer',
            'reminder_days_before_first' => 'integer',
            'reminder_days_before_due' => 'integer',
            'reminder_days_after_due' => 'integer',
        ];
    }

    public static function instance(): self
    {
        return static::query()->firstOrCreate(
            [],
            [
                'is_enabled' => config('billing-automation.is_enabled', true),
                'run_at_time' => config('billing-automation.run_at_time', '09:00:00'),
                'service_order_days_before' => config('billing-automation.service_order_days_before', 14),
                'domain_order_days_before' => config('billing-automation.domain_order_days_before', 30),
                'reminder_days_before_first' => config('billing-automation.reminder_days_before_first', 7),
                'reminder_days_before_due' => config('billing-automation.reminder_days_before_due', 0),
                'reminder_days_after_due' => config('billing-automation.reminder_days_after_due', 14),
                'send_order_created_email' => true,
                'send_reminder_emails' => true,
            ],
        );
    }

    public function shouldRunAt(CarbonInterface $moment): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        $configured = Carbon::parse((string) $this->run_at_time)->format('H:i');

        return $configured === $moment->format('H:i');
    }
}
