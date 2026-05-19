<?php

use App\Enums\IntervalUnit;
use App\Enums\RegistrarTldPriceOperation;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\RegistrarTldPrice;

test('domain billing interval uses registrar tld price term years', function () {
    $registrar = Registrar::query()->create([
        'name' => 'Interval Registrar',
        'code' => 'interval-'.uniqid(),
        'is_active' => true,
    ]);

    $tld = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'pt',
    ]);

    $price = RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Renew,
        'term_years' => 2,
        'amount_minor' => 2_000,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    [$unit, $count] = Domain::billingIntervalFromRegistrarTld($tld, $price);

    expect($unit)->toBe(IntervalUnit::Year)
        ->and($count)->toBe(2);
});

test('registrar tld default billing interval prefers renew price', function () {
    $registrar = Registrar::query()->create([
        'name' => 'Default Interval Registrar',
        'code' => 'default-interval-'.uniqid(),
        'is_active' => true,
    ]);

    $tld = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'com',
    ]);

    RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Register,
        'term_years' => 1,
        'amount_minor' => 1_000,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    RegistrarTldPrice::query()->create([
        'registrar_tld_id' => $tld->id,
        'operation' => RegistrarTldPriceOperation::Renew,
        'term_years' => 3,
        'amount_minor' => 3_000,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    [$unit, $count] = $tld->defaultBillingInterval(RegistrarTldPriceOperation::Renew);

    expect($unit)->toBe(IntervalUnit::Year)
        ->and($count)->toBe(3);
});
