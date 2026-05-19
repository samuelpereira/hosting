<?php

use App\Livewire\Billing\Orders\Manage as OrdersManage;
use App\Models\BillingTaxSetting;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;

test('order totals update when unit or quantity changes', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
    ]);

    $client = Client::query()->create([
        'name' => 'Order Tax Co',
        'email' => 'order-tax@example.test',
        'currency' => 'EUR',
        'is_active' => true,
        'vat_exception' => false,
        'irs_exception' => false,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $component = Livewire::test(OrdersManage::class)
        ->set('client_id', $client->id)
        ->set('lineItems.0.unit_amount_input', '100.00')
        ->set('lineItems.0.quantity', 1);

    expect($component->viewData('orderTotals')['tax_minor'])->toBe(2300)
        ->and($component->viewData('orderTotals')['irs_minor'])->toBe(2500)
        ->and($component->viewData('orderTotals')['total_minor'])->toBe(9800);

    $component
        ->set('lineItems.0.quantity', 2)
        ->assertSet('lineItems.0.tax_amount_input', '46.00');

    expect($component->viewData('orderTotals')['tax_minor'])->toBe(4600)
        ->and($component->viewData('orderTotals')['irs_minor'])->toBe(5000)
        ->and($component->viewData('orderTotals')['total_minor'])->toBe(19_600);
});

test('order totals have zero vat when client has vat exception', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
    ]);

    $client = Client::query()->create([
        'name' => 'VAT Exempt Co',
        'email' => 'vat-exempt@example.test',
        'currency' => 'EUR',
        'is_active' => true,
        'vat_exception' => true,
        'irs_exception' => false,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $component = Livewire::test(OrdersManage::class)
        ->set('client_id', $client->id)
        ->set('lineItems.0.unit_amount_input', '100.00')
        ->set('lineItems.0.quantity', 1);

    expect($component->viewData('orderTotals')['tax_minor'])->toBe(0)
        ->and($component->viewData('orderTotals')['irs_minor'])->toBe(2500);
});

test('order line shows vat only label for domain when domains irs exception enabled', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
        'domains_irs_exception' => true,
    ]);

    $client = Client::query()->create([
        'name' => 'Line Label Co',
        'email' => 'line-label@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Billing\Orders\Manage::class)
        ->set('client_id', $client->id)
        ->set('lineItems.0.type', 'domain')
        ->assertSee(__('VAT only'));
});

test('order line shows no tax badge in green when client is fully exempt', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
    ]);

    $client = Client::query()->create([
        'name' => 'Exempt Co',
        'email' => 'exempt@example.test',
        'currency' => 'EUR',
        'is_active' => true,
        'vat_exception' => true,
        'irs_exception' => true,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Billing\Orders\Manage::class)
        ->set('client_id', $client->id)
        ->assertSee(__('No tax'));
});

test('order line shows vat and irs label for service lines', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
        'domains_irs_exception' => true,
    ]);

    $client = Client::query()->create([
        'name' => 'Service Label Co',
        'email' => 'service-label@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Billing\Orders\Manage::class)
        ->set('client_id', $client->id)
        ->set('lineItems.0.type', 'service')
        ->assertSee(__('VAT + IRS'));
});

test('order save persists subtotal tax irs and total', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create([
        'vat_rate_percent' => 23,
        'irs_rate_percent' => 25,
    ]);

    $client = Client::query()->create([
        'name' => 'Save Tax Co',
        'email' => 'save-tax@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(OrdersManage::class)
        ->set('client_id', $client->id)
        ->set('number', 'ORD-TAX-TEST-1')
        ->set('lineItems.0.description', 'Hosting')
        ->set('lineItems.0.unit_amount_input', '100.00')
        ->set('lineItems.0.quantity', 1)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $order = \App\Models\Order::query()->where('number', 'ORD-TAX-TEST-1')->first();
    expect($order)->not->toBeNull()
        ->and($order->subtotal_minor)->toBe(10_000)
        ->and($order->tax_minor)->toBe(2300)
        ->and($order->irs_minor)->toBe(2500)
        ->and($order->total_minor)->toBe(9800);

    $item = $order->items()->first();
    expect($item->tax_amount_minor)->toBe(2300);
});
