<?php

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Enums\RegistrarTldPriceOperation;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\RegistrarTldPrice;

test('domain monthly cost comes from linked tld price not domain record', function () {
    $registrar = Registrar::query()->create([
        'name' => 'Cost Registrar',
        'code' => 'cost-'.uniqid(),
        'is_active' => true,
    ]);

    $tld = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'pt',
    ]);

    $price = RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Renew,
        'term_years' => 1,
        'amount_minor' => 20_00,
        'domain_cost_minor' => 12_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $domain = Domain::query()->create([
        'client_id' => Client::query()->create([
            'name' => 'Cost Client',
            'email' => 'cost@example.test',
            'currency' => 'EUR',
            'is_active' => true,
        ])->id,
        'registrar_id' => $registrar->id,
        'registrar_tld_id' => $tld->id,
        'registrar_tld_price_id' => $price->id,
        'fqdn' => 'cost.example.pt',
        'status' => DomainStatus::Active,
        'currency' => 'EUR',
        'recurring_amount_minor' => 20_00,
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'registered_at' => now()->subYear(),
        'expires_at' => now()->addYear(),
        'due_at' => now()->addYear(),
    ]);

    expect($domain->monthlyCostMinor())->toBe(1_00);
});
