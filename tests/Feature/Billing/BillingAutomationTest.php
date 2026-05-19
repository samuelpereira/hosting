<?php

use App\Enums\DomainStatus;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\ServiceStatus;
use App\Livewire\Billing\AutomationSettings\Manage as AutomationSettingsManage;
use App\Models\BillingAutomationSetting;
use App\Models\BillingTaxSetting;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Product;
use App\Models\Registrar;
use App\Models\Service;
use App\Models\User;
use App\Services\Billing\BillingAutomationRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function createTestRegistrar(): Registrar
{
    return Registrar::query()->create([
        'name' => 'Test Registrar',
        'code' => 'test-'.uniqid(),
        'is_active' => true,
    ]);
}

function createTestDomain(
    Client $client,
    Registrar $registrar,
    string $fqdn,
    \Carbon\CarbonInterface $dueAt,
    int $recurringMinor = 1_000,
    DomainStatus $status = DomainStatus::Active,
    bool $autoRenew = true,
): Domain {
    return Domain::query()->create([
        'client_id' => $client->id,
        'registrar_id' => $registrar->id,
        'fqdn' => $fqdn,
        'status' => $status,
        'currency' => 'EUR',
        'recurring_amount_minor' => $recurringMinor,
        'interval_unit' => 'year',
        'interval_count' => 1,
        'registered_at' => now()->subYear(),
        'expires_at' => $dueAt,
        'due_at' => $dueAt,
        'auto_renew' => $autoRenew,
    ]);
}

beforeEach(function () {
    Mail::fake();
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create(['vat_rate_percent' => 23, 'irs_rate_percent' => 25]);
    BillingAutomationSetting::query()->delete();
    BillingAutomationSetting::query()->create([
        'is_enabled' => true,
        'run_at_time' => '09:00:00',
        'service_order_days_before' => 14,
        'domain_order_days_before' => 30,
        'reminder_days_before_first' => 7,
        'reminder_days_before_due' => 0,
        'reminder_days_after_due' => 14,
        'send_order_created_email' => false,
        'send_reminder_emails' => false,
    ]);
});

test('automation settings page loads', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->get(route('billing.automation.edit'))->assertOk();
});

test('automation settings can be saved', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(AutomationSettingsManage::class)
        ->set('service_order_days_before', 10)
        ->set('run_at_time', '10:30')
        ->call('save')
        ->assertHasNoErrors();

    $settings = BillingAutomationSetting::instance()->fresh();
    expect($settings->service_order_days_before)->toBe(10)
        ->and(Carbon::parse($settings->run_at_time)->format('H:i'))->toBe('10:30');
});

test('creates service renewal order with product and synced domain lines', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Auto Client',
        'email' => 'auto@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Hosting Plan',
        'slug' => 'hosting-auto',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(14)->startOfDay();

    $registrar = createTestRegistrar();
    $domain = createTestDomain($client, $registrar, 'example-auto.test', $dueAt, 1_500);

    $service = Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 10_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'domain_id' => $domain->id,
        'status' => ServiceStatus::Active,
        'due_at' => $dueAt,
    ]);

    $runner = app(BillingAutomationRunner::class);
    $result = $runner->run(force: true);

    expect($result['orders_created'])->toBe(1);

    $order = Order::query()->where('client_id', $client->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Unpaid)
        ->and($order->due_at?->toDateString())->toBe($dueAt->toDateString())
        ->and($order->items)->toHaveCount(2);

    $types = $order->items->pluck('type')->map(fn ($t) => $t->value)->all();
    $serviceLine = $order->items->firstWhere('type', OrderItemType::Service);
    $domainLine = $order->items->firstWhere('type', OrderItemType::Domain);
    expect($types)->toContain(OrderItemType::Service->value, OrderItemType::Domain->value)
        ->and($serviceLine?->description)->toBe($service->renewalOrderLineDescription($dueAt))
        ->and($domainLine?->description)->toBe($domain->renewalOrderLineDescription());

    Carbon::setTestNow();
});

test('creates standalone domain renewal order', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Domain Only',
        'email' => 'domain-only@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(30)->startOfDay();

    $registrar = createTestRegistrar();
    createTestDomain($client, $registrar, 'standalone.test', $dueAt, 2_000);

    $runner = app(BillingAutomationRunner::class);
    $result = $runner->run(force: true);

    expect($result['orders_created'])->toBe(1);

    $order = Order::query()->where('client_id', $client->id)->first();
    $domain = Domain::query()->where('fqdn', 'standalone.test')->first();
    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->type)->toBe(OrderItemType::Domain)
        ->and($order->items->first()->description)->toBe($domain->renewalOrderLineDescription());

    Carbon::setTestNow();
});

test('skips domain when covered by active service', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Linked',
        'email' => 'linked@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Plan',
        'slug' => 'plan-linked',
        'is_active' => true,
    ]);

    $serviceDue = now()->addDays(14)->startOfDay();
    $domainDue = now()->addDays(30)->startOfDay();

    $registrar = createTestRegistrar();
    $domain = createTestDomain($client, $registrar, 'linked.test', $domainDue, 1_000);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 5_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'domain_id' => $domain->id,
        'status' => ServiceStatus::Active,
        'due_at' => $serviceDue,
    ]);

    $runner = app(BillingAutomationRunner::class);
    $result = $runner->run(force: true);

    expect($result['orders_created'])->toBe(1)
        ->and(Order::query()->where('client_id', $client->id)->count())->toBe(1);

    Carbon::setTestNow();
});

test('catch-up creates renewal orders missed on previous days', function () {
    Carbon::setTestNow('2026-05-22 09:00:00');

    $client = Client::query()->create([
        'name' => 'Catch-up Client',
        'email' => 'catchup@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Plan',
        'slug' => 'plan-catchup',
        'is_active' => true,
    ]);

    // Order should have been created on May 8 (14 days before due); automation was down until May 22.
    $dueAt = Carbon::parse('2026-05-22')->startOfDay();

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 8_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'status' => ServiceStatus::Active,
        'due_at' => $dueAt,
    ]);

    $runner = app(BillingAutomationRunner::class);
    $result = $runner->run(force: true);

    expect($result['orders_created'])->toBe(1)
        ->and(Order::query()->where('client_id', $client->id)->count())->toBe(1);

    Carbon::setTestNow();
});

test('does not create renewal order for inactive service', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Suspended Client',
        'email' => 'suspended@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Plan',
        'slug' => 'plan-suspended',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(14)->startOfDay();

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 5_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'status' => ServiceStatus::Suspended,
        'due_at' => $dueAt,
    ]);

    $result = app(BillingAutomationRunner::class)->run(force: true);

    expect($result['orders_created'])->toBe(0);

    Carbon::setTestNow();
});

test('service renewal order omits synced domain when domain is not active or expired', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Cancelled Domain Client',
        'email' => 'cancelled-domain@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Plan',
        'slug' => 'plan-cancelled-domain',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(14)->startOfDay();
    $registrar = createTestRegistrar();
    $domain = createTestDomain($client, $registrar, 'cancelled.test', $dueAt, 1_500, DomainStatus::Cancelled);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 10_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'domain_id' => $domain->id,
        'status' => ServiceStatus::Active,
        'due_at' => $dueAt,
    ]);

    $result = app(BillingAutomationRunner::class)->run(force: true);

    expect($result['orders_created'])->toBe(1);

    $order = Order::query()->where('client_id', $client->id)->first();
    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->type)->toBe(OrderItemType::Service);

    Carbon::setTestNow();
});

test('does not create standalone domain renewal order when auto renew is disabled', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'No Auto Renew',
        'email' => 'no-auto@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(30)->startOfDay();
    $registrar = createTestRegistrar();
    createTestDomain($client, $registrar, 'no-auto.test', $dueAt, 2_000, DomainStatus::Active, autoRenew: false);

    $result = app(BillingAutomationRunner::class)->run(force: true);

    expect($result['orders_created'])->toBe(0);

    Carbon::setTestNow();
});

test('service renewal order omits synced domain when auto renew is disabled', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'No Auto Renew Bundle',
        'email' => 'no-auto-bundle@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Plan',
        'slug' => 'plan-no-auto-bundle',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(14)->startOfDay();
    $registrar = createTestRegistrar();
    $domain = createTestDomain($client, $registrar, 'no-auto-bundle.test', $dueAt, 1_500, autoRenew: false);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 10_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'domain_id' => $domain->id,
        'status' => ServiceStatus::Active,
        'due_at' => $dueAt,
    ]);

    $result = app(BillingAutomationRunner::class)->run(force: true);

    expect($result['orders_created'])->toBe(1);

    $order = Order::query()->where('client_id', $client->id)->first();
    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->type)->toBe(OrderItemType::Service);

    Carbon::setTestNow();
});

test('creates standalone domain renewal order for expired domain with auto renew', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Expired Auto',
        'email' => 'expired-auto@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(30)->startOfDay();
    $registrar = createTestRegistrar();
    createTestDomain($client, $registrar, 'expired-auto.test', $dueAt, 2_000, DomainStatus::Expired, autoRenew: true);

    $result = app(BillingAutomationRunner::class)->run(force: true);

    expect($result['orders_created'])->toBe(1);

    Carbon::setTestNow();
});

test('service renewal order includes synced domain when domain is expired', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Expired Domain Client',
        'email' => 'expired-domain@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Plan',
        'slug' => 'plan-expired-domain',
        'is_active' => true,
    ]);

    $dueAt = now()->addDays(14)->startOfDay();
    $registrar = createTestRegistrar();
    $domain = createTestDomain($client, $registrar, 'expired.test', $dueAt, 1_500, DomainStatus::Expired);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 10_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'domain_id' => $domain->id,
        'status' => ServiceStatus::Active,
        'due_at' => $dueAt,
    ]);

    $result = app(BillingAutomationRunner::class)->run(force: true);

    expect($result['orders_created'])->toBe(1);

    $order = Order::query()->where('client_id', $client->id)->first();
    expect($order->items)->toHaveCount(2);

    $types = $order->items->pluck('type')->map(fn ($t) => $t->value)->all();
    expect($types)->toContain(OrderItemType::Service->value, OrderItemType::Domain->value);

    Carbon::setTestNow();
});

test('does not create renewal order before lead time window', function () {
    Carbon::setTestNow('2026-05-19 09:00:00');

    $client = Client::query()->create([
        'name' => 'Early Client',
        'email' => 'early@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Plan',
        'slug' => 'plan-early',
        'is_active' => true,
    ]);

    // Due Jun 10 → create on May 27; today is May 19 (too early).
    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 5_000,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'status' => ServiceStatus::Active,
        'due_at' => now()->addDays(22)->startOfDay(),
    ]);

    $result = app(BillingAutomationRunner::class)->run(force: true);

    expect($result['orders_created'])->toBe(0);

    Carbon::setTestNow();
});

test('does not run outside configured time without force', function () {
    Carbon::setTestNow('2026-05-19 15:00:00');

    $result = app(BillingAutomationRunner::class)->run();

    expect($result['skipped_time'])->toBeTrue()
        ->and($result['orders_created'])->toBe(0);

    Carbon::setTestNow();
});
