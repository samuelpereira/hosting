<?php

use App\Models\BillingCompanySetting;
use Database\Seeders\BillingCompanySettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing company setting seeder stores built-in defaults when config is empty', function () {
    config([
        'billing.company' => [
            'name' => null,
            'address_line' => null,
            'postal_code' => null,
            'city' => null,
            'country' => null,
            'phone' => null,
            'email' => null,
            'bank_account' => null,
        ],
    ]);

    $this->seed(BillingCompanySettingSeeder::class);

    $settings = BillingCompanySetting::instance()->fresh();

    expect($settings->name)->toBeNull()
        ->and($settings->email)->toBeNull();
});

test('billing company setting seeder prefers config over built-in defaults', function () {
    config([
        'billing.company' => [
            'name' => 'Custom Billing Co',
            'email' => 'billing@custom.test',
        ],
    ]);

    $this->seed(BillingCompanySettingSeeder::class);

    $settings = BillingCompanySetting::instance()->fresh();

    expect($settings->name)->toBe('Custom Billing Co')
        ->and($settings->email)->toBe('billing@custom.test');
});
