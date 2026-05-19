<?php

return [

    'is_enabled' => (bool) env('BILLING_AUTOMATION_ENABLED', true),

    'run_at_time' => env('BILLING_AUTOMATION_RUN_AT', '09:00'),

    'service_order_days_before' => (int) env('BILLING_AUTOMATION_SERVICE_DAYS_BEFORE', 14),

    'domain_order_days_before' => (int) env('BILLING_AUTOMATION_DOMAIN_DAYS_BEFORE', 30),

    'reminder_days_before_first' => (int) env('BILLING_AUTOMATION_REMINDER_DAYS_BEFORE_FIRST', 7),

    'reminder_days_before_due' => (int) env('BILLING_AUTOMATION_REMINDER_DAYS_BEFORE_DUE', 0),

    'reminder_days_after_due' => (int) env('BILLING_AUTOMATION_REMINDER_DAYS_AFTER_DUE', 14),

];
