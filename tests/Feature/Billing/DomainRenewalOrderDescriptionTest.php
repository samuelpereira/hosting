<?php

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Registrar;
use Illuminate\Support\Carbon;

test('renewal order line description uses due_at and billing interval', function () {
    $client = Client::query()->create([
        'name' => 'Desc Client',
        'email' => 'desc@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $registrar = Registrar::query()->create([
        'name' => 'Desc Registrar',
        'code' => 'desc-'.uniqid(),
        'is_active' => true,
    ]);

    $domain = Domain::query()->create([
        'client_id' => $client->id,
        'registrar_id' => $registrar->id,
        'fqdn' => 'example.pt',
        'status' => DomainStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 2,
        'registered_at' => '2024-01-01',
        'expires_at' => '2026-06-01',
        'due_at' => '2026-06-01',
        'auto_renew' => true,
    ]);

    $start = Carbon::parse('2026-06-01');
    $end = $start->copy()->addYears(2);

    expect($domain->renewalOrderLineDescription())->toBe(
        sprintf('Renovação de Domínio - example.pt (%s - %s)', $start->format('d/m/Y'), $end->format('d/m/Y')),
    );
});
