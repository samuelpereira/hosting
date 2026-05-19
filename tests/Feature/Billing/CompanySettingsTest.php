<?php

use App\Livewire\Billing\CompanySettings\Manage as CompanySettingsManage;
use App\Models\BillingCompanySetting;
use App\Models\User;
use Livewire\Livewire;

test('company details page loads for verified users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->get(route('billing.company.edit'))->assertOk();
});

test('company details can be saved', function () {
    BillingCompanySetting::query()->delete();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(CompanySettingsManage::class)
        ->set('name', 'Caetanix Lda')
        ->set('address_line', 'Rua Example 1')
        ->set('postal_code', '1000-001')
        ->set('city', 'Lisboa')
        ->set('country', 'pt')
        ->set('phone', '+351 210 000 000')
        ->set('email', 'billing@caetanix.pt')
        ->set('bank_account', 'IBAN PT50 0000 0000 0000 0000 0000 0')
        ->call('save')
        ->assertHasNoErrors();

    $settings = BillingCompanySetting::instance()->fresh();
    expect($settings->name)->toBe('Caetanix Lda')
        ->and($settings->postal_code)->toBe('1000-001')
        ->and($settings->country)->toBe('PT')
        ->and($settings->bank_account)->toContain('IBAN');
});

test('company name is used in order email variables', function () {
    BillingCompanySetting::query()->delete();
    BillingCompanySetting::query()->create(['name' => 'My Hosting Co']);

    $vars = BillingCompanySetting::instance()->emailVariables();
    expect($vars['company_name'])->toBe('My Hosting Co');
});
