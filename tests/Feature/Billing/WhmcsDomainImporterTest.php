<?php

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
