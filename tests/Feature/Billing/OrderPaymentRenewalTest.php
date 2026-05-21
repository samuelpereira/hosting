<?php

use App\Enums\IntervalUnit;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\ServiceStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Billing\Transactions\Manage as TransactionsManage;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('completed payment extends service and linked domain billing dates', function () {
    Carbon::setTestNow('2026-05-20');

    $client = Client::query()->create([
        'name' => 'Renewal Client',
        'email' => 'renewal@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $domain = Domain::query()->create([
        'client_id' => $client->id,
        'fqdn' => 'example.pt',
        'status' => \App\Enums\DomainStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'expires_at' => '2026-06-01',
        'due_at' => '2026-06-01',
    ]);

    $service = Service::query()->create([
        'client_id' => $client->id,
        'product_id' => Product::query()->create([
            'type' => \App\Enums\ProductType::Hosting,
            'name' => 'Hosting',
            'slug' => 'hosting-renewal',
            'is_active' => true,
        ])->id,
        'status' => ServiceStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'domain_id' => $domain->id,
        'due_at' => '2026-04-15',
    ]);

    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-RENEW-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 20_00,
        'tax_minor' => 0,
        'total_minor' => 20_00,
        'issued_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'position' => 0,
        'type' => OrderItemType::Service,
        'description' => 'Hosting renewal',
        'quantity' => 1,
        'unit_amount_minor' => 20_00,
        'tax_amount_minor' => 0,
        'service_id' => $service->id,
    ]);

    Livewire::test(TransactionsManage::class)
        ->set('order_id', $order->id)
        ->set('status', TransactionStatus::Completed->value)
        ->set('currency', 'EUR')
        ->set('amount_input', '20.00')
        ->set('paid_at', '2026-05-20')
        ->call('save')
        ->assertHasNoErrors();

    $service->refresh();
    $domain->refresh();

    expect($service->due_at?->format('Y-m-d'))->toBe('2026-05-15')
        ->and($domain->due_at?->format('Y-m-d'))->toBe('2026-05-15')
        ->and($domain->expires_at?->format('Y-m-d'))->toBe('2027-06-01');
});

test('domain-only order line extends domain due and expires dates', function () {
    Carbon::setTestNow('2026-05-20');

    $client = Client::query()->create([
        'name' => 'Domain Client',
        'email' => 'domain-renewal@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $domain = Domain::query()->create([
        'client_id' => $client->id,
        'fqdn' => 'only-domain.pt',
        'status' => \App\Enums\DomainStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 2,
        'expires_at' => '2025-01-10',
        'due_at' => '2025-01-10',
    ]);

    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-DOM-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 30_00,
        'tax_minor' => 0,
        'total_minor' => 30_00,
        'issued_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'position' => 0,
        'type' => OrderItemType::Domain,
        'description' => 'Domain renewal',
        'quantity' => 1,
        'unit_amount_minor' => 30_00,
        'tax_amount_minor' => 0,
        'domain_id' => $domain->id,
    ]);

    $order->applyCompletedPayment(Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 30_00,
        'paid_at' => '2026-05-20',
    ]));

    $domain->refresh();

    expect($domain->due_at?->format('Y-m-d'))->toBe('2027-01-10')
        ->and($domain->expires_at?->format('Y-m-d'))->toBe('2027-01-10');
});

test('payment does not double-extend dates when order is already paid', function () {
    Carbon::setTestNow('2026-05-20');

    $service = Service::query()->create([
        'client_id' => Client::query()->create([
            'name' => 'C',
            'email' => 'c@test.example',
            'currency' => 'EUR',
            'is_active' => true,
        ])->id,
        'product_id' => Product::query()->create([
            'type' => \App\Enums\ProductType::Vps,
            'name' => 'VPS',
            'slug' => 'vps-double',
            'is_active' => true,
        ])->id,
        'status' => ServiceStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'due_at' => '2026-05-01',
    ]);

    $order = Order::query()->create([
        'client_id' => $service->client_id,
        'number' => 'ORD-DBL',
        'status' => OrderStatus::Paid,
        'currency' => 'EUR',
        'subtotal_minor' => 10_00,
        'tax_minor' => 0,
        'total_minor' => 10_00,
        'paid_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'position' => 0,
        'type' => OrderItemType::Service,
        'description' => 'VPS',
        'quantity' => 1,
        'unit_amount_minor' => 10_00,
        'tax_amount_minor' => 0,
        'service_id' => $service->id,
    ]);

    $order->applyCompletedPayment(Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 10_00,
        'paid_at' => now(),
    ]));

    expect($service->refresh()->due_at?->format('Y-m-d'))->toBe('2026-05-01');
});
