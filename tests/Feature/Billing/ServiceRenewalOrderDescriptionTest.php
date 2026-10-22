<?php

use App\Enums\IntervalUnit;
use App\Enums\ProductType;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Carbon;

test('service renewal order line description uses due_at domain and billing interval', function () {
    $client = Client::query()->create([
        'name' => 'Service Desc Client',
        'email' => 'svc-desc@example.test',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'type' => ProductType::Hosting,
        'name' => 'Hosting Plan',
        'slug' => 'hosting-desc',
        'is_active' => true,
    ]);

    $service = Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 5_000,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 3,
        'associated_domain' => 'client.example.pt',
        'status' => ServiceStatus::Active,
        'due_at' => '2026-06-01',
    ]);

    $start = Carbon::parse('2026-06-01');
    $end = $start->copy()->addMonths(3);

    expect($service->renewalOrderLineDescription())->toBe(
        sprintf('Hosting Plan - client.example.pt (%s - %s)', $start->format('d/m/Y'), $end->format('d/m/Y')),
    );
});
