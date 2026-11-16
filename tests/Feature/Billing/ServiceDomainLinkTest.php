<?php

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Enums\ProductType;
use App\Enums\ServiceStatus;
use App\Livewire\Billing\Services\Manage as ServicesManage;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Registrar;
use App\Models\Service;
use Livewire\Livewire;

function serviceLinkClient(): Client
{
    return Client::query()->create([
        'name' => 'Link Client',
        'email' => 'link-'.uniqid().'@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
}

function serviceLinkDomain(Client $client, string $fqdn): Domain
{
    return Domain::query()->create([
        'client_id' => $client->id,
        'registrar_id' => Registrar::query()->create([
            'name' => 'Link Registrar',
            'code' => 'link-'.uniqid(),
            'is_active' => true,
        ])->id,
        'fqdn' => $fqdn,
        'status' => DomainStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Year,
        'interval_count' => 1,
        'registered_at' => '2025-06-01',
        'expires_at' => '2026-06-01',
        'due_at' => '2026-06-01',
    ]);
}

function serviceLinkService(Client $client, ?Domain $domain = null): Service
{
    return Service::query()->create([
        'client_id' => $client->id,
        'product_id' => Product::query()->create([
            'type' => ProductType::Hosting,
            'name' => 'Hosting',
            'slug' => 'link-'.uniqid(),
            'is_active' => true,
        ])->id,
        'status' => ServiceStatus::Active,
        'currency' => 'EUR',
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'due_at' => '2026-06-01',
        'domain_id' => $domain?->id,
        'associated_domain' => $domain?->fqdn,
    ]);
}

test('an existing domain can be linked to a service', function () {
    $client = serviceLinkClient();
    $domain = serviceLinkDomain($client, 'link-me.pt');
    $service = serviceLinkService($client);

    Livewire::test(ServicesManage::class, ['service' => $service])
        ->assertSee('link-me.pt')
        ->set('linked_domain_id', $domain->id)
        ->assertSet('associated_domain', 'link-me.pt')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->refresh()->domain_id)->toBe($domain->id)
        ->and($service->associated_domain)->toBe('link-me.pt');
});

test('the linked domain can be changed to another one', function () {
    $client = serviceLinkClient();
    $old = serviceLinkDomain($client, 'old-link.pt');
    $new = serviceLinkDomain($client, 'new-link.pt');
    $service = serviceLinkService($client, $old);

    Livewire::test(ServicesManage::class, ['service' => $service])
        ->assertSet('linked_domain_id', $old->id)
        ->set('linked_domain_id', $new->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($service->refresh()->domain_id)->toBe($new->id)
        ->and($service->associated_domain)->toBe('new-link.pt');
});

test('the domain can be unlinked from a service', function () {
    $client = serviceLinkClient();
    $domain = serviceLinkDomain($client, 'unlink-me.pt');
    $service = serviceLinkService($client, $domain);

    Livewire::test(ServicesManage::class, ['service' => $service])
        ->set('linked_domain_id', null)
        ->call('save')
        ->assertHasNoErrors();

    expect($service->refresh()->domain_id)->toBeNull()
        ->and(Domain::query()->whereKey($domain->id)->exists())->toBeTrue();
});

test('a domain from another client cannot be linked', function () {
    $client = serviceLinkClient();
    $foreign = serviceLinkDomain(serviceLinkClient(), 'not-yours.pt');
    $service = serviceLinkService($client);

    Livewire::test(ServicesManage::class, ['service' => $service])
        ->assertDontSee('not-yours.pt')
        ->set('linked_domain_id', $foreign->id)
        ->call('save')
        ->assertHasErrors(['linked_domain_id']);

    expect($service->refresh()->domain_id)->toBeNull();
});
