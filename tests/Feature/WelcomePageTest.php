<?php

use App\Enums\IntervalUnit;
use App\Enums\ProductPriceBillingKind;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductPrice;

test('welcome page lists public products only', function () {
    $public = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'Public VPS Plan',
        'slug' => 'public-vps-plan',
        'description' => 'A visible plan',
        'is_active' => true,
        'is_public' => true,
    ]);
    ProductPrice::query()->create([
        'product_id' => $public->id,
        'billing_kind' => ProductPriceBillingKind::Recurring,
        'interval_unit' => IntervalUnit::Month,
        'interval_count' => 1,
        'amount_minor' => 15_00,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    Product::query()->create([
        'type' => ProductType::Hosting,
        'name' => 'Private Plan',
        'slug' => 'private-plan',
        'is_active' => true,
        'is_public' => false,
    ]);

    Product::query()->create([
        'type' => ProductType::Hosting,
        'name' => 'Inactive Public',
        'slug' => 'inactive-public',
        'is_active' => false,
        'is_public' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Public VPS Plan', false)
        ->assertSee('A visible plan', false)
        ->assertSee('15.00', false)
        ->assertSee('EUR', false)
        ->assertDontSee('Private Plan', false)
        ->assertSee('Inactive Public', false);
});

test('welcome page orders public products by sort order', function () {
    Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'Third Plan',
        'slug' => 'third-plan',
        'is_public' => true,
        'sort_order' => 30,
    ]);
    Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'First Plan',
        'slug' => 'first-plan',
        'is_public' => true,
        'sort_order' => 10,
    ]);
    Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'Second Plan',
        'slug' => 'second-plan',
        'is_public' => true,
        'sort_order' => 20,
    ]);

    $response = $this->get(route('home'))->assertOk();

    $posFirst = strpos($response->getContent(), 'First Plan');
    $posSecond = strpos($response->getContent(), 'Second Plan');
    $posThird = strpos($response->getContent(), 'Third Plan');

    expect($posFirst)->toBeInt()->toBeLessThan($posSecond)
        ->and($posSecond)->toBeLessThan($posThird);
});

test('welcome page shows empty state when no public products', function () {
    Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'Internal Only',
        'slug' => 'internal-only',
        'is_active' => true,
        'is_public' => false,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('No plans are available at the moment. Please check back soon.'), false)
        ->assertDontSee('Internal Only', false);
});
