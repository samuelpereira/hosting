<?php

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Billing\Orders\Manage as OrdersManage;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Registrar;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function createDomainRenewalOrderForForm(OrderStatus $status = OrderStatus::Unpaid): array
{
    $client = Client::query()->create([
        'name' => 'Form Client',
        'email' => 'form-'.uniqid().'@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $domain = Domain::query()->create([
        'client_id' => $client->id,
        'registrar_id' => Registrar::query()->create([
            'name' => 'Form Registrar',
            'code' => 'form-'.uniqid(),
            'is_active' => true,
        ])->id,
        'fqdn' => 'form-'.uniqid().'.pt',
        'status' => DomainStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'registered_at' => '2025-06-01',
        'expires_at' => '2026-06-01',
        'due_at' => '2026-06-01',
    ]);

    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-FORM-'.uniqid(),
        'status' => $status,
        'currency' => 'EUR',
        'subtotal_minor' => 15_00,
        'tax_minor' => 0,
        'total_minor' => 15_00,
        'issued_at' => now(),
        'due_at' => '2026-06-01',
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'position' => 0,
        'type' => OrderItemType::Domain,
        'description' => 'Domain renewal',
        'quantity' => 1,
        'unit_amount_minor' => 15_00,
        'tax_amount_minor' => 0,
        'domain_id' => $domain->id,
    ]);

    return [$order, $domain];
}

test('saving the order form keeps the domain link on line items', function () {
    Carbon::setTestNow('2026-05-20');

    [$order, $domain] = createDomainRenewalOrderForForm();

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->set('notes', 'Edited before payment')
        ->call('save')
        ->assertHasNoErrors();

    expect($order->refresh()->items)->toHaveCount(1)
        ->and($order->items->first()->domain_id)->toBe($domain->id);
});

test('paying an order after editing it still renews the domain', function () {
    Carbon::setTestNow('2026-05-20');

    [$order, $domain] = createDomainRenewalOrderForForm();

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->call('save')
        ->assertHasNoErrors();

    // The form recalculates VAT on save, so pay whatever the order total now is.
    $order->refresh()->applyCompletedPayment(Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => $order->total_minor,
        'paid_at' => now(),
    ]));

    expect($domain->refresh()->due_at?->format('Y-m-d'))->toBe('2027-06-01')
        ->and($domain->expires_at?->format('Y-m-d'))->toBe('2027-06-01');
});

test('warns when status is set to paid manually without renewal', function () {
    [$order] = createDomainRenewalOrderForForm();

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->assertDontSee('will not be renewed')
        ->set('status', OrderStatus::Paid->value)
        ->assertSee('will not be renewed');
});

test('does not warn for orders already renewed by a payment', function () {
    [$order] = createDomainRenewalOrderForForm(OrderStatus::Paid);
    $order->update(['renewal_applied_at' => now()]);

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->assertDontSee('will not be renewed');
});
