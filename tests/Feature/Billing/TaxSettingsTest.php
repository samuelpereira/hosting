<?php

use App\Livewire\Billing\TaxSettings\Manage as TaxSettingsManage;
use App\Models\BillingTaxSetting;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;

test('tax settings page loads for verified users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->get(route('billing.tax-settings.edit'))->assertOk();
});

test('tax settings can be saved', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(TaxSettingsManage::class)
        ->set('vat_rate_input', '21.50')
        ->set('irs_rate_input', '24.00')
        ->call('save')
        ->assertHasNoErrors();

    $settings = BillingTaxSetting::instance()->fresh();
    expect((float) $settings->vat_rate_percent)->toBe(21.5)
        ->and((float) $settings->irs_rate_percent)->toBe(24.0);
});

test('client effective tax rates respect exceptions', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
    ]);

    $client = Client::query()->create([
        'name' => 'Tax Client',
        'email' => 'tax-client@example.test',
        'currency' => 'EUR',
        'is_active' => true,
        'vat_exception' => true,
        'irs_exception' => false,
    ]);

    expect($client->effectiveVatRatePercent())->toBe(0.0)
        ->and($client->effectiveIrsRatePercent())->toBe(25.0);

    $client->update(['vat_exception' => false, 'irs_exception' => true]);

    expect($client->fresh()->effectiveVatRatePercent())->toBe(23.0)
        ->and($client->fresh()->effectiveIrsRatePercent())->toBe(0.0);
});

test('billing tax setting computes vat and irs per line', function () {
    BillingTaxSetting::query()->delete();
    $settings = BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
    ]);

    $client = Client::query()->create([
        'name' => 'Tax Client Two',
        'email' => 'tax-client-two@example.test',
        'currency' => 'EUR',
        'is_active' => true,
        'vat_exception' => false,
        'irs_exception' => false,
    ]);

    expect($settings->vatMinorForLine($client, 10_000))->toBe(2300)
        ->and($settings->irsMinorForLine($client, 10_000))->toBe(2500);

    $client->update(['vat_exception' => true]);
    expect($settings->vatMinorForLine($client->fresh(), 10_000))->toBe(0)
        ->and($settings->irsMinorForLine($client->fresh(), 10_000))->toBe(2500);
});

test('domains irs exception skips irs on domain lines only', function () {
    BillingTaxSetting::query()->delete();
    $settings = BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
        'domains_irs_exception' => true,
    ]);

    $client = Client::query()->create([
        'name' => 'Domain IRS Client',
        'email' => 'domain-irs@example.test',
        'currency' => 'EUR',
        'is_active' => true,
        'vat_exception' => false,
        'irs_exception' => false,
    ]);

    expect($settings->irsMinorForLine($client, 10_000, false))->toBe(2500)
        ->and($settings->irsMinorForLine($client, 10_000, true))->toBe(0);
});
