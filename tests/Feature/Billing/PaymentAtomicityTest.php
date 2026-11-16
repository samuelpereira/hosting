<?php

use App\Enums\IntervalUnit;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\ServiceStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Billing\Orders\Manage as OrdersManage;
use App\Livewire\Billing\Transactions\Manage as TransactionsManage;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function createAtomicityServiceOrder(): array
{
    $client = Client::query()->create([
        'name' => 'Atomic Client',
        'email' => 'atomic-'.uniqid().'@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $service = Service::query()->create([
        'client_id' => $client->id,
        'product_id' => Product::query()->create([
            'type' => ProductType::Hosting,
            'name' => 'Hosting',
            'slug' => 'atomic-'.uniqid(),
            'is_active' => true,
        ])->id,
        'status' => ServiceStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'due_at' => '2026-05-01',
    ]);

    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-ATOMIC-'.uniqid(),
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

    return [$order, $service];
}

function failServiceUpdates(): void
{
    Service::updating(fn () => throw new RuntimeException('Renewal failed'));
}

afterEach(function () {
    Service::flushEventListeners();
});

test('payment is not recorded when renewal fails', function () {
    Carbon::setTestNow('2026-05-20');

    [$order, $service] = createAtomicityServiceOrder();
    failServiceUpdates();

    expect(fn () => Livewire::test(TransactionsManage::class)
        ->set('order_id', $order->id)
        ->set('status', TransactionStatus::Completed->value)
        ->set('currency', 'EUR')
        ->set('amount_input', '20.00')
        ->set('paid_at', '2026-05-20')
        ->call('save'))->toThrow(RuntimeException::class);

    expect(Transaction::query()->where('order_id', $order->id)->count())->toBe(0)
        ->and($order->refresh()->status)->toBe(OrderStatus::Unpaid)
        ->and($order->renewal_applied_at)->toBeNull()
        ->and($service->refresh()->due_at?->format('Y-m-d'))->toBe('2026-05-01');
});

test('transaction edit is rolled back when renewal fails', function () {
    Carbon::setTestNow('2026-05-20');

    [$order, $service] = createAtomicityServiceOrder();

    $tx = Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Pending,
        'currency' => 'EUR',
        'amount_minor' => 20_00,
        'paid_at' => '2026-05-20',
    ]);

    failServiceUpdates();

    expect(fn () => Livewire::test(TransactionsManage::class, ['transaction' => $tx])
        ->set('status', TransactionStatus::Completed->value)
        ->call('save'))->toThrow(RuntimeException::class);

    expect($tx->refresh()->status)->toBe(TransactionStatus::Pending)
        ->and($order->refresh()->status)->toBe(OrderStatus::Unpaid)
        ->and($service->refresh()->due_at?->format('Y-m-d'))->toBe('2026-05-01');
});

test('deleting a payment keeps its document until the delete is committed', function () {
    Carbon::setTestNow('2026-05-20');
    Storage::fake('local');

    [$order] = createAtomicityServiceOrder();
    Storage::disk('local')->put('transaction-documents/receipt.pdf', 'pdf');

    $tx = Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 20_00,
        'paid_at' => '2026-05-20',
        'document_path' => 'transaction-documents/receipt.pdf',
        'document_name' => 'receipt.pdf',
    ]);
    $order->refresh()->applyCompletedPayment($tx);

    Order::updating(fn () => throw new RuntimeException('Sync failed'));

    try {
        expect(fn () => Livewire::test(OrdersManage::class, ['order' => $order->refresh()])
            ->call('deletePayment', $tx->id))->toThrow(RuntimeException::class);
    } finally {
        Order::flushEventListeners();
    }

    expect(Transaction::query()->whereKey($tx->id)->exists())->toBeTrue()
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);
    Storage::disk('local')->assertExists('transaction-documents/receipt.pdf');

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->call('deletePayment', $tx->id);

    expect(Transaction::query()->whereKey($tx->id)->exists())->toBeFalse()
        ->and($order->refresh()->status)->toBe(OrderStatus::Unpaid);
    Storage::disk('local')->assertMissing('transaction-documents/receipt.pdf');
});
