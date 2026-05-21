<?php

use App\Enums\IntervalUnit;
use App\Enums\RegistrarTldPriceOperation;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\ProductPriceBillingKind;
use App\Enums\ProductType;
use App\Enums\ServerStatus;
use App\Enums\ServiceStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Billing\RegistrarTlds\Manage as RegistrarTldsManage;
use App\Livewire\Billing\Services\Manage as ServicesManage;
use App\Livewire\Billing\Transactions\Manage as TransactionsManage;
use App\Models\BillingTaxSetting;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\RegistrarTldPrice;
use App\Models\Server;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Livewire\Billing\Orders\Manage as OrdersManage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('billing index routes respond for verified users', function (string $route) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->get(route($route))->assertOk();
})->with([
    'billing.clients.index',
    'billing.products.index',
    'billing.servers.index',
    'billing.services.index',
    'billing.registrars.index',
    'billing.registrar-tlds.index',
    'billing.domains.index',
    'billing.orders.index',
    'billing.reports.integrated-position',
    'billing.transactions.index',
    'billing.email-templates.index',
    'billing.company.edit',
    'billing.tax-settings.edit',
]);

test('registrar tld normalize matches dotted and undotted labels', function () {
    $registrar = Registrar::query()->create([
        'name' => 'OVH',
        'code' => 'ovh-match',
        'is_active' => true,
    ]);
    $existing = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'edu.pt',
    ]);

    expect(RegistrarTld::findMatchingId('.edu.pt', $registrar->id))->toBe($existing->id)
        ->and(RegistrarTld::findMatchingId('edu.pt'))->toBe($existing->id)
        ->and(RegistrarTld::findMatchingId('com', $registrar->id))->toBeNull();
});

test('tld edit syncs price rows on save', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $registrar = Registrar::query()->create([
        'name' => 'Test Registrar',
        'code' => 'test-reg',
        'is_active' => true,
    ]);
    $tld = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'com',
    ]);
    $existing = RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Register,
        'term_years' => 1,
        'amount_minor' => 10_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Renew,
        'term_years' => 1,
        'amount_minor' => 8_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    Livewire::test(RegistrarTldsManage::class, ['registrarTld' => $tld])
        ->set('priceRows', [
            [
                'id' => $existing->id,
                'operation' => RegistrarTldPriceOperation::Register->value,
                'term_years' => 1,
                'currency' => 'EUR',
                'amount_input' => '12.50',
                'domain_cost_input' => '8.00',
                'is_active' => true,
            ],
            [
                'id' => null,
                'operation' => RegistrarTldPriceOperation::Transfer->value,
                'term_years' => 2,
                'currency' => 'EUR',
                'amount_input' => '20.00',
                'domain_cost_input' => '15.00',
                'is_active' => true,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('billing.registrar-tlds.edit', $tld));

    $tld->refresh();
    expect($tld->prices)->toHaveCount(2);
    expect($tld->prices->firstWhere('id', $existing->id)?->amount_minor)->toBe(1250)
        ->and($tld->prices->firstWhere('id', $existing->id)?->domain_cost_minor)->toBe(800);
    expect($tld->prices->contains(
        fn (RegistrarTldPrice $p) => $p->operation === RegistrarTldPriceOperation::Transfer
            && $p->term_years === 2
            && $p->amount_minor === 2000
            && $p->domain_cost_minor === 1500
    ))->toBeTrue();
    expect($tld->prices->contains(
        fn (RegistrarTldPrice $p) => $p->operation === RegistrarTldPriceOperation::Renew
    ))->toBeFalse();
});

test('service save rejects ram over server capacity', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Test Co',
        'email' => 'c@test.example',
        'currency' => 'USD',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'VPS',
        'slug' => 'vps-test',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'name' => 'S1',
        'hostname' => 's1.test.internal',
        'status' => ServerStatus::Active,
        'cost_amount_minor' => 0,
        'cost_currency' => 'USD',
        'specs' => ['ram_gb' => 1],
    ]);
    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => ServiceStatus::Active,
        'currency' => 'USD',
        'allocated_ram_gb' => 1,
        'allocated_disk_gb' => null,
    ]);

    Livewire::test(ServicesManage::class)
        ->set('client_id', $client->id)
        ->set('product_id', $product->id)
        ->set('server_id', $server->id)
        ->set('status', ServiceStatus::Active->value)
        ->set('allocated_ram_gb', 2)
        ->call('save')
        ->assertHasErrors(['allocated_ram_gb']);
});

test('service save allows over server capacity when server allows overallocation', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Overalloc Client',
        'email' => 'overalloc@test.example',
        'currency' => 'USD',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'VPS Overalloc',
        'slug' => 'vps-overalloc',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'name' => 'Overalloc S1',
        'hostname' => 'overalloc-s1.test.internal',
        'status' => ServerStatus::Active,
        'allow_overallocation' => true,
        'cost_amount_minor' => 0,
        'cost_currency' => 'USD',
        'specs' => ['ram_gb' => 2, 'disk_gb' => 50],
    ]);
    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => ServiceStatus::Active,
        'currency' => 'USD',
        'allocated_ram_gb' => 2,
        'allocated_disk_gb' => 40,
    ]);

    Livewire::test(ServicesManage::class)
        ->set('client_id', $client->id)
        ->set('product_id', $product->id)
        ->set('server_id', $server->id)
        ->set('status', ServiceStatus::Active->value)
        ->set('allocated_ram_gb', 8)
        ->set('allocated_disk_gb', 30)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Service::query()->where('client_id', $client->id)->where('allocated_ram_gb', 8)->where('allocated_disk_gb', 30)->exists())->toBeTrue();
});

test('new service prefills amounts from product prices', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Price Client',
        'email' => 'price@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'Starter VPS',
        'slug' => 'starter-vps',
        'is_active' => true,
    ]);
    ProductPrice::query()->create([
        'product_id' => $product->id,
        'billing_kind' => ProductPriceBillingKind::OneTime,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'amount_minor' => 25_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    ProductPrice::query()->create([
        'product_id' => $product->id,
        'billing_kind' => ProductPriceBillingKind::Recurring,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'amount_minor' => 15_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    ProductPrice::query()->create([
        'product_id' => $product->id,
        'billing_kind' => ProductPriceBillingKind::Recurring,
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'amount_minor' => 150_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    Livewire::test(ServicesManage::class)
        ->set('client_id', $client->id)
        ->set('product_id', $product->id)
        ->assertSet('first_payment_amount_input', '15.00')
        ->assertSet('recurring_amount_input', '15.00')
        ->assertSet('interval_unit', 'month')
        ->assertSet('interval_count', 1)
        ->set('interval_unit', 'year')
        ->assertSet('interval_unit', 'year')
        ->assertSet('recurring_amount_input', '150.00')
        ->assertSet('first_payment_amount_input', '150.00')
        ->assertSet('activated_at', now()->format('Y-m-d'))
        ->assertSet('due_at', now()->addYear()->format('Y-m-d'));
});

test('order create with service_id prefills line with product and domain', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Acme',
        'email' => 'a@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'Pro VPS',
        'slug' => 'pro-vps',
        'is_active' => true,
    ]);
    $service = Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 42_50,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'server_id' => null,
        'domain_id' => null,
        'associated_domain' => 'billing.example.net',
        'status' => ServiceStatus::Active,
        'due_at' => '2026-07-15',
        'allocated_ram_gb' => null,
        'allocated_disk_gb' => null,
    ]);

    $expectedDescription = $service->renewalOrderLineDescription();

    Livewire::withQueryParams(['service_id' => $service->id])
        ->test(OrdersManage::class)
        ->assertSet('client_id', $client->id)
        ->assertSet('lineItems.0.type', OrderItemType::Service->value)
        ->assertSet('lineItems.0.service_id', $service->id)
        ->assertSet('lineItems.0.description', $expectedDescription)
        ->assertSet('lineItems.0.unit_amount_input', '42.50');

    $response = $this->get(route('billing.orders.create', ['service_id' => $service->id]));
    $response->assertOk();
    expect($response->getContent())->toContain('billing.example.net')
        ->and($response->getContent())->toContain('42.50');
});

test('service edit page lists orders linked via line items', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Co',
        'email' => 'co@test.example',
        'currency' => 'USD',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'VPS',
        'slug' => 'vps-ord',
        'is_active' => true,
    ]);
    $service = Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'USD',
        'server_id' => null,
        'domain_id' => null,
        'associated_domain' => null,
        'status' => ServiceStatus::Active,
        'allocated_ram_gb' => null,
        'allocated_disk_gb' => null,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-SVC-LINK-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'USD',
        'subtotal_minor' => 10_00,
        'tax_minor' => 0,
        'total_minor' => 10_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => null,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'position' => 0,
        'type' => OrderItemType::Service,
        'description' => 'VPS hosting',
        'quantity' => 1,
        'unit_amount_minor' => 10_00,
        'tax_amount_minor' => 0,
        'service_id' => $service->id,
    ]);

    $this->get(route('billing.services.edit', $service))
        ->assertOk()
        ->assertSee('ORD-SVC-LINK-1', false);
});

test('recording a completed payment marks the order paid with transaction paid_at', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Pay Co',
        'email' => 'pay@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-PAY-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 50_00,
        'tax_minor' => 0,
        'total_minor' => 50_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => null,
    ]);

    Livewire::test(TransactionsManage::class)
        ->set('order_id', $order->id)
        ->set('status', TransactionStatus::Completed->value)
        ->set('currency', 'EUR')
        ->set('amount_input', '50.00')
        ->set('paid_at', '2026-03-15')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $order->refresh();
    $transaction = $order->transactions()->first();
    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->paid_at?->format('Y-m-d'))->toBe('2026-03-15');
    expect($transaction?->reference)->toMatch('/^[A-Z0-9]{6}$/');
});

test('record payment form prefills a generated reference', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Ref Co',
        'email' => 'ref@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-REF-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 10_00,
        'tax_minor' => 0,
        'total_minor' => 10_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => null,
    ]);

    Livewire::test(TransactionsManage::class)
        ->assertSet('reference', fn (string $ref) => (bool) preg_match('/^[A-Z0-9]{6}$/', $ref));
});

test('deleting a payment from order edit reverts order to unpaid', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Del Pay Co',
        'email' => 'del@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-DEL-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 30_00,
        'tax_minor' => 0,
        'total_minor' => 30_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => null,
    ]);
    $transaction = Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 30_00,
        'paid_at' => '2026-03-15',
    ]);
    $order->applyCompletedPayment($transaction);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid);

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->call('deletePayment', $transaction->id)
        ->assertHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Unpaid);
    expect($order->paid_at)->toBeNull();
    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse();
});

test('order can be deleted from edit page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Delete Order Co',
        'email' => 'delete-order@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-DELETE-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 50_00,
        'tax_minor' => 0,
        'irs_minor' => 0,
        'total_minor' => 50_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => null,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'position' => 0,
        'type' => OrderItemType::Manual,
        'description' => 'Test line',
        'quantity' => 1,
        'unit_amount_minor' => 50_00,
        'tax_amount_minor' => 0,
    ]);
    Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 25_00,
        'paid_at' => now(),
    ]);

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->call('deleteOrder')
        ->assertRedirect(route('billing.orders.index'));

    expect(Order::query()->whereKey($order->id)->exists())->toBeFalse()
        ->and(OrderItem::query()->where('order_id', $order->id)->exists())->toBeFalse()
        ->and(Transaction::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('recording a payment can attach a document', function () {
    Storage::fake('local');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Doc Co',
        'email' => 'doc@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-DOC-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 25_00,
        'tax_minor' => 0,
        'total_minor' => 25_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => null,
    ]);

    $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

    Livewire::test(TransactionsManage::class)
        ->set('order_id', $order->id)
        ->set('status', TransactionStatus::Completed->value)
        ->set('currency', 'EUR')
        ->set('amount_input', '25.00')
        ->set('paid_at', '2026-03-15')
        ->set('document', $file)
        ->call('save')
        ->assertHasNoErrors();

    $transaction = $order->fresh()->transactions()->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->document_name)->toBe('receipt.pdf');
    expect($transaction->hasDocument())->toBeTrue();

    $this->get(route('billing.transactions.document', $transaction))
        ->assertOk()
        ->assertDownload('receipt.pdf');

    $path = $transaction->document_path;

    Livewire::test(TransactionsManage::class, ['transaction' => $transaction])
        ->call('removeDocument')
        ->assertHasNoErrors();

    $transaction->refresh();
    expect($transaction->document_path)->toBeNull()
        ->and($transaction->document_name)->toBeNull()
        ->and($transaction->hasDocument())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});

test('product show page lists overview and services', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Prod Client',
        'email' => 'prod@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'Starter VPS',
        'slug' => 'starter-vps-show',
        'description' => 'A starter plan',
        'default_allocated_ram_gb' => 2,
        'default_allocated_disk_gb' => 50,
        'is_active' => true,
    ]);
    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => ServiceStatus::Active,
        'currency' => 'EUR',
        'recurring_amount_minor' => 15_00,
    ]);

    $this->get(route('billing.products.show', $product))
        ->assertOk()
        ->assertSee('Starter VPS', false)
        ->assertSee('starter-vps-show', false)
        ->assertSee('A starter plan', false)
        ->assertSee('Prod Client', false)
        ->assertSee(__('Services using this product'), false);
});

test('server show page lists overview and services', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Srv Client',
        'email' => 'srv@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'VPS',
        'slug' => 'vps-srv-show',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'name' => 'Node A',
        'hostname' => 'node-a.test.internal',
        'status' => ServerStatus::Active,
        'cost_amount_minor' => 50_00,
        'cost_currency' => 'EUR',
        'specs' => ['ram_gb' => 64, 'disk_gb' => 512, 'cpu' => '12 vCPU'],
    ]);
    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => ServiceStatus::Active,
        'currency' => 'EUR',
        'allocated_ram_gb' => 4,
        'allocated_disk_gb' => 50,
    ]);

    $this->get(route('billing.servers.show', $server))
        ->assertOk()
        ->assertSee('Node A', false)
        ->assertSee('node-a.test.internal', false)
        ->assertSee('12 vCPU', false)
        ->assertSee('Srv Client', false)
        ->assertSee(__('Services on this server'), false);
});

test('client show overview includes stats headings', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);
    $client = Client::query()->create([
        'name' => 'Overview Co',
        'email' => 'ov@test.example',
        'currency' => 'USD',
        'is_active' => true,
        'registered_at' => '2024-03-15',
        'vat_exception' => true,
        'irs_exception' => false,
    ]);

    $irsRate = number_format((float) BillingTaxSetting::instance()->irs_rate_percent, 2);

    $this->get(route('billing.clients.show', $client))
        ->assertOk()
        ->assertSee(__('Payments received'), false)
        ->assertSee(__('Products (via services)'), false)
        ->assertSee(__('Registered at'), false)
        ->assertSee('2024-03-15', false)
        ->assertSee(__('VAT'), false)
        ->assertSee(__('exception'), false)
        ->assertSee(__('IRS'), false)
        ->assertSee("{$irsRate}%", false);
});
