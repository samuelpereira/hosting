<?php

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Enums\ProductType;
use App\Enums\RegistrarTldPriceOperation;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\RegistrarTldPrice;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Services\Billing\IntegratedPositionReport;
use Livewire\Livewire;

test('integrated position report route responds for verified users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->get(route('billing.reports.integrated-position'))->assertOk();
});

test('integrated position report calculates monthly revenue expense and profit', function () {
    $client = Client::query()->create([
        'name' => 'Acme Lda',
        'email' => 'acme@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'name' => 'Hosting Pro',
        'slug' => 'hosting-pro-report',
        'type' => ProductType::Hosting,
        'is_active' => true,
    ]);

    $registrar = Registrar::query()->create([
        'name' => 'Test Registrar',
        'code' => 'test-report',
        'is_active' => true,
    ]);

    $server = Server::query()->create([
        'name' => 'Node A',
        'hostname' => 'node-a.test',
        'cost_amount_minor' => 100_00,
        'cost_currency' => 'EUR',
    ]);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 120_00,
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'status' => ServiceStatus::Active,
    ]);

    $tld = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'pt',
    ]);

    $renewPrice = RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Renew,
        'term_years' => 1,
        'amount_minor' => 24_00,
        'domain_cost_minor' => 12_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    Domain::query()->create([
        'client_id' => $client->id,
        'registrar_id' => $registrar->id,
        'registrar_tld_id' => $tld->id,
        'registrar_tld_price_id' => $renewPrice->id,
        'fqdn' => 'example.pt',
        'status' => DomainStatus::Active,
        'currency' => 'EUR',
        'recurring_amount_minor' => 24_00,
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'registered_at' => now()->subYear(),
        'expires_at' => now()->addYear(),
        'due_at' => now()->addYear(),
    ]);

    $report = app(IntegratedPositionReport::class)->build();

    expect($report['products'])->toHaveCount(1)
        ->and($report['products'][0]['label'])->toBe('Hosting Pro')
        ->and($report['products'][0]['service_count'])->toBe(1)
        ->and($report['products'][0]['revenue_minor'])->toBe(10_00)
        ->and($report['products'][0]['expense_minor'])->toBe(100_00)
        ->and($report['products'][0]['profit_minor'])->toBe(-90_00);

    expect($report['domains'])->toHaveCount(1)
        ->and($report['domains'][0]['revenue_yearly_minor'])->toBe(24_00)
        ->and($report['domains'][0]['expense_yearly_minor'])->toBe(12_00)
        ->and($report['domains'][0]['profit_yearly_minor'])->toBe(12_00)
        ->and($report['domains'][0]['revenue_minor'])->toBe(2_00)
        ->and($report['domains'][0]['expense_minor'])->toBe(1_00)
        ->and($report['domains'][0]['profit_minor'])->toBe(1_00)
        ->and($report['domains_yearly_totals_by_currency']['EUR']['revenue_minor'])->toBe(24_00);

    expect($report['summary_by_currency']['EUR']['revenue_minor'])->toBe(12_00)
        ->and($report['summary_by_currency']['EUR']['expense_minor'])->toBe(101_00)
        ->and($report['summary_by_currency']['EUR']['profit_minor'])->toBe(-89_00)
        ->and($report['summary_yearly_by_currency']['EUR']['revenue_minor'])->toBe(144_00)
        ->and($report['summary_yearly_by_currency']['EUR']['expense_minor'])->toBe(1212_00)
        ->and($report['summary_yearly_by_currency']['EUR']['profit_minor'])->toBe(-1068_00);
});

test('zero recurring services do not share server cost allocation', function () {
    $client = Client::query()->create([
        'name' => 'Zero Recurring Client',
        'email' => 'zero-rec@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'name' => 'Paid Hosting',
        'slug' => 'paid-hosting-report',
        'type' => ProductType::Hosting,
        'is_active' => true,
    ]);

    $freeProduct = Product::query()->create([
        'name' => 'Free Slot',
        'slug' => 'free-slot-report',
        'type' => ProductType::Hosting,
        'is_active' => true,
    ]);

    $server = Server::query()->create([
        'name' => 'Shared Node',
        'hostname' => 'shared.test',
        'cost_amount_minor' => 100_00,
        'cost_currency' => 'EUR',
    ]);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 60_00,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'status' => ServiceStatus::Active,
    ]);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $freeProduct->id,
        'server_id' => $server->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 0,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'status' => ServiceStatus::Active,
    ]);

    $report = app(IntegratedPositionReport::class)->build();

    expect($report['products'])->toHaveCount(2);

    $paid = collect($report['products'])->firstWhere('label', 'Paid Hosting');
    $free = collect($report['products'])->firstWhere('label', 'Free Slot');

    expect($paid['expense_minor'])->toBe(100_00)
        ->and($paid['service_count'])->toBe(1)
        ->and($free['expense_minor'])->toBe(0)
        ->and($free['service_count'])->toBe(1)
        ->and($report['servers'][0]['active_services'])->toBe(1)
        ->and($report['servers'][0]['unallocated_minor'])->toBe(0);
});

test('multiple active services on same product are aggregated into one row', function () {
    $client = Client::query()->create([
        'name' => 'Aggregate Client',
        'email' => 'agg@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'name' => 'Shared Product',
        'slug' => 'shared-product-report',
        'type' => ProductType::Hosting,
        'is_active' => true,
    ]);

    foreach ([30_00, 20_00] as $recurring) {
        Service::query()->create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'currency' => 'EUR',
            'recurring_amount_minor' => $recurring,
            'interval_unit' => IntervalUnit::Month,
            'interval_count' => 1,
            'status' => ServiceStatus::Active,
        ]);
    }

    $report = app(IntegratedPositionReport::class)->build();

    expect($report['products'])->toHaveCount(1)
        ->and($report['products'][0]['label'])->toBe('Shared Product')
        ->and($report['products'][0]['service_count'])->toBe(2)
        ->and($report['products'][0]['revenue_minor'])->toBe(50_00);
});

test('integrated position livewire page shows service and domain labels', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $client = Client::query()->create([
        'name' => 'Beta SA',
        'email' => 'beta@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'name' => 'VPS Small',
        'slug' => 'vps-small-report',
        'type' => ProductType::Hosting,
        'is_active' => true,
    ]);

    $registrar = Registrar::query()->create([
        'name' => 'Registrar UI',
        'code' => 'reg-ui-report',
        'is_active' => true,
    ]);

    Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 10_00,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'status' => ServiceStatus::Active,
    ]);

    $tld = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'pt',
    ]);

    RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Renew,
        'term_years' => 1,
        'amount_minor' => 20_00,
        'domain_cost_minor' => 5_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    Domain::query()->create([
        'client_id' => $client->id,
        'registrar_id' => $registrar->id,
        'registrar_tld_id' => $tld->id,
        'fqdn' => 'beta.pt',
        'status' => DomainStatus::Active,
        'currency' => 'EUR',
        'recurring_amount_minor' => 20_00,
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'registered_at' => now()->subYear(),
        'expires_at' => now()->addYear(),
        'due_at' => now()->addYear(),
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Billing\Reports\IntegratedPosition::class)
        ->assertSee('VPS Small')
        ->assertSee('beta.pt')
        ->assertSee('Integrated position');
});
