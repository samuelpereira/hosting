<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\Whmcs\WhmcsHostingImporter;
use App\Services\Whmcs\WhmcsProductImporter;
use App\Services\Whmcs\WhmcsProductMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('whmcs product importer links mapped names without creating products', function () {
    Schema::create('tblproducts', function ($table) {
        $table->unsignedBigInteger('id')->primary();
        $table->string('name');
    });

    DB::table('tblproducts')->insert([
        ['id' => 42, 'name' => 'WHMCS VPS 50G'],
        ['id' => 99, 'name' => 'Unmapped Product'],
    ]);

    $catalog = Product::query()->create([
        'type' => ProductType::Vps,
        'name' => 'VPS 50G',
        'slug' => 'vps-50',
        'is_active' => true,
    ]);

    $countBefore = Product::query()->count();

    $importer = new WhmcsProductImporter(
        connectionName: config('database.default'),
        productsTable: 'tblproducts',
        columns: ['id' => 'id', 'name' => 'name'],
        productMap: new WhmcsProductMap(['names' => ['WHMCS VPS 50G' => 'vps-50']]),
    );

    $stats = $importer->import();

    expect(Product::query()->count())->toBe($countBefore)
        ->and($catalog->fresh()->whmcs_id)->toBe(42)
        ->and($stats['linked'])->toBe(1)
        ->and($stats['skipped'])->toBe(1)
        ->and($stats['errors'])->toContain('WHMCS product #99 (Unmapped Product): not in product_name_map');

    Schema::dropIfExists('tblproducts');
});

test('hosting import resolves packageid when another whmcs product already owns whmcs_id on slug', function () {
    Schema::create('tblproducts', function ($table) {
        $table->unsignedBigInteger('id')->primary();
        $table->string('name');
    });

    DB::table('tblproducts')->insert([
        ['id' => 5, 'name' => 'Web M'],
        ['id' => 16, 'name' => 'Webmin Web M'],
    ]);

    $product = Product::query()->create([
        'type' => ProductType::Hosting,
        'name' => 'Web Hosting M',
        'slug' => 'web-m',
        'whmcs_id' => 5,
        'is_active' => true,
    ]);

    $importer = new WhmcsHostingImporter(
        connectionName: config('database.default'),
        hostingTable: 'tblhosting',
        productsTable: 'tblproducts',
        columns: config('whmcs-import.hosting_columns'),
        productColumns: ['id' => 'id', 'name' => 'name'],
        defaultProductId: null,
        productMap: new WhmcsProductMap([
            'names' => [
                'Web M' => 'web-m',
                'Webmin Web M' => 'web-m',
            ],
        ]),
    );

    $ref = new ReflectionMethod($importer, 'loadMaps');
    $ref->setAccessible(true);
    $ref->invoke($importer);

    $packageRef = new ReflectionProperty($importer, 'productIdByWhmcsPackageId');
    $packageRef->setAccessible(true);
    $byPackage = $packageRef->getValue($importer);

    expect($byPackage[16] ?? null)->toBe($product->id)
        ->and($byPackage[5] ?? null)->toBe($product->id);

    Schema::dropIfExists('tblproducts');
});
