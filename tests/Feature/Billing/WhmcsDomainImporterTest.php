<?php

use App\Enums\IntervalUnit;
use App\Models\Client;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Services\Whmcs\WhmcsDomainImporter;

function makeWhmcsDomainImporter(?int $defaultRegistrarId = null): WhmcsDomainImporter
{
    return new WhmcsDomainImporter(
        connectionName: 'sqlite',
        domainsTable: 'tbldomains',
        columns: config('whmcs-import.domain_columns'),
        defaultRegistrarId: $defaultRegistrarId,
        activeStatuses: config('whmcs-import.domain_active_statuses'),
    );
}

function callImporterPrivate(object $importer, string $method, mixed ...$args): mixed
{
    $ref = new ReflectionMethod($importer, $method);
    $ref->setAccessible(true);

    return $ref->invoke($importer, ...$args);
}

test('whmcs domain importer does not create unknown registrars', function () {
    $default = Registrar::query()->create([
        'name' => 'Default',
        'code' => 'default',
        'is_active' => true,
    ]);

    $importer = makeWhmcsDomainImporter($default->id);
    callImporterPrivate($importer, 'loadRegistrarMap');

    $countBefore = Registrar::query()->count();

    $resolved = callImporterPrivate($importer, 'resolveRegistrarId', 'Some Unknown Registrar');

    expect(Registrar::query()->count())->toBe($countBefore)
        ->and($resolved)->toBe($default->id);
});

test('whmcs domain importer uses registrationperiod for interval_count', function () {
    $default = Registrar::query()->create([
        'name' => 'Default',
        'code' => 'default-period',
        'is_active' => true,
    ]);

    $importer = makeWhmcsDomainImporter($default->id);

    $row = (object) [
        'id' => 99,
        'userid' => 1,
        'domain' => 'example.pt',
        'registrar' => 'default-period',
        'registrationdate' => '2024-01-01',
        'registrationperiod' => 3,
        'expirydate' => '2027-01-01',
        'nextduedate' => '2027-01-01',
        'status' => 'Active',
    ];

    $period = callImporterPrivate($importer, 'registrationPeriodFromRow', $row);
    expect($period)->toBe(3);

    Client::query()->create([
        'whmcs_id' => 1,
        'name' => 'Period Client',
        'email' => 'period@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    callImporterPrivate($importer, 'loadClientMap');
    callImporterPrivate($importer, 'loadRegistrarMap');
    callImporterPrivate($importer, 'loadRegistrarTldMap');

    $payload = callImporterPrivate($importer, 'mapRow', $row, Client::query()->where('whmcs_id', 1)->value('id'), 'example.pt', 99);

    expect($payload['interval_unit'])->toBe(IntervalUnit::Year->value)
        ->and($payload['interval_count'])->toBe(3);
});

test('whmcs domain importer falls back to tld interval when registrationperiod is missing', function () {
    $default = Registrar::query()->create([
        'name' => 'Default',
        'code' => 'default-fallback',
        'is_active' => true,
    ]);

    $tld = RegistrarTld::query()->create([
        'registrar_id' => $default->id,
        'tld' => 'pt',
    ]);

    $tld->prices()->create([
        'operation' => \App\Enums\RegistrarTldPriceOperation::Renew,
        'term_years' => 2,
        'amount_minor' => 2_000,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $importer = makeWhmcsDomainImporter($default->id);

    $row = (object) [
        'id' => 100,
        'userid' => 2,
        'domain' => 'fallback.pt',
        'registrar' => 'default-fallback',
        'registrationdate' => '2024-01-01',
        'registrationperiod' => 0,
        'expirydate' => '2026-01-01',
        'nextduedate' => '2026-01-01',
        'status' => 'Active',
    ];

    Client::query()->create([
        'whmcs_id' => 2,
        'name' => 'Fallback Client',
        'email' => 'fallback@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    callImporterPrivate($importer, 'loadClientMap');
    callImporterPrivate($importer, 'loadRegistrarMap');
    callImporterPrivate($importer, 'loadRegistrarTldMap');

    $payload = callImporterPrivate(
        $importer,
        'mapRow',
        $row,
        Client::query()->where('whmcs_id', 2)->value('id'),
        'fallback.pt',
        100,
    );

    expect($payload['interval_unit'])->toBe(IntervalUnit::Year->value)
        ->and($payload['interval_count'])->toBe(2);
});

test('whmcs domain importer creates new tlds under default registrar', function () {
    $known = Registrar::query()->create([
        'name' => 'OVH',
        'code' => 'ovh',
        'is_active' => true,
    ]);
    $default = Registrar::query()->create([
        'name' => 'Import bucket',
        'code' => 'import-bucket',
        'is_active' => true,
    ]);

    $importer = makeWhmcsDomainImporter($default->id);
    callImporterPrivate($importer, 'loadRegistrarTldMap');

    $tldId = callImporterPrivate($importer, 'resolveRegistrarTldId', $known->id, 'xyz');

    $tld = RegistrarTld::query()->findOrFail($tldId);

    expect($tld->registrar_id)->toBe($default->id)
        ->and($tld->tld)->toBe('xyz');
});
