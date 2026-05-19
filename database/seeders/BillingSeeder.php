<?php

namespace Database\Seeders;

use App\Enums\IntervalUnit;
use App\Enums\ProductPriceBillingKind;
use App\Enums\ProductType;
use App\Enums\RegistrarTldPriceOperation;
use App\Enums\ServerStatus;
use App\Models\BillingAutomationSetting;
use App\Models\BillingTaxSetting;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\RegistrarTldPrice;
use App\Models\Server;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    /**
     * Hosting product catalog. Prices are in minor units (e.g. 1_00 = €1.00).
     *
     * @var list<array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     disk_gb: int,
     *     ram_gb: int|null,
     *     month_minor: int,
     *     year_minor: int,
     * }>
     */
    private const PRODUCTS = [
        [
            'slug' => 'server-management',
            'name' => 'Gestão de Servidor',
            'description' => 'Gestão de Servidor',
            'type' => ProductType::Service,
            'disk_gb' => null,
            'ram_gb' => null,
            'month_count' => 6,
            'month_minor' => 300_00,
        ],
        [
            'slug' => 'vps-1-4-50',
            'name' => 'Servidor Privado 1-4-50',
            'description' => 'Virtual Private Server - 1 vCPU, 4GB RAM, 50GB SSD',
            'type' => ProductType::Vps,
            'disk_gb' => 50,
            'ram_gb' => 4,
            'year_minor' => 110_00,
        ],
        [
            'slug' => 'vps-2-8-400',
            'name' => 'Servidor Privado 2-8-400',
            'description' => 'Virtual Private Server - 2 vCPU, 8GB RAM, 400GB SSD',
            'type' => ProductType::Vps,
            'disk_gb' => 400,
            'ram_gb' => 8,
            'year_minor' => 300_00,
        ],
        [
            'slug' => 'vps-6-12-200',
            'name' => 'Servidor Privado 6-12-200',
            'description' => 'Virtual Private Server - 6 vCPU, 12GB RAM, 200GB SSD',
            'type' => ProductType::Vps,
            'disk_gb' => 200,
            'ram_gb' => 12,
            'year_minor' => 456_00,
        ],
        [
            'slug' => 'dedicated-12-64-512',
            'name' => 'Servidor Dedicado 12-64-512',
            'description' => 'Dedicated Server - 12 vCPU, 64GB RAM, 512GB SSD, Unlimited Traffic',
            'type' => ProductType::Dedicated,
            'disk_gb' => 512,
            'ram_gb' => 64,
            'month_minor' => 69_00,
        ],
        [
            'slug' => 'resell-s',
            'name' => 'Reseller S',
            'description' => 'Reseller Hosting',
            'type' => ProductType::Reseller,
            'disk_gb' => 0,
            'ram_gb' => null,
            'year_minor' => 0_00,
        ],
        [
            'slug' => 'resell-m',
            'name' => 'Reseller M',
            'description' => 'Reseller Hosting',
            'type' => ProductType::Reseller,
            'disk_gb' => 0,
            'ram_gb' => null,
            'year_minor' => 0_00,
        ],
        [
            'slug' => 'resell-l',
            'name' => 'Reseller L',
            'description' => 'Reseller Hosting',
            'type' => ProductType::Reseller,
            'disk_gb' => 0,
            'ram_gb' => null,
            'year_minor' => 0_00,
        ],
        [
            'slug' => 'web-s',
            'name' => 'Web Hosting S',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 2,
            'ram_gb' => null,
            'month_minor' => 1_50,
            'year_minor' => 18_00,
        ],
        [
            'slug' => 'web-m',
            'name' => 'Web Hosting M',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 5,
            'ram_gb' => null,
            'month_minor' => 2_50,
            'year_minor' => 30_00,
        ],
        [
            'slug' => 'web-l',
            'name' => 'Web Hosting L',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 10,
            'ram_gb' => null,
            'month_minor' => 3_50,
            'year_minor' => 42_00,
        ],
        [
            'slug' => 'web-xl',
            'name' => 'Web Hosting XL',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 20,
            'ram_gb' => null,
            'month_minor' => 5_50,
            'year_minor' => 66_00,
        ],
        [
            'slug' => 'web-xl-plus-one',
            'name' => 'Web Hosting XL+',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 50,
            'ram_gb' => null,
            'month_minor' => 8_50,
            'year_minor' => 102_00,
        ],
        [
            'slug' => 'web-xl-plus-two',
            'name' => 'Web Hosting XL++',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 100,
            'ram_gb' => null,
            'month_minor' => 12_50,
            'year_minor' => 150_00,
        ],
        [
            'slug' => 'web-xl-plus-tree',
            'name' => 'Web Hosting XL+++',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 200,
            'ram_gb' => null,
            'month_minor' => 22_50,
            'year_minor' => 270_00,
        ],
        [
            'slug' => 'web-mail-5g',
            'name' => 'Web Hosting Mail 5G',
            'description' => 'Webmin Hosting',
            'type' => ProductType::Hosting,
            'disk_gb' => 50,
            'ram_gb' => null,
            'month_minor' => 10_00,
            'year_minor' => 120_00,
        ],
    ];

    /**
     * Registrars, TLDs, and per-operation prices (minor units). Optional per TLD: term_years, currency.
     *
     * @var list<array{
     *     code: string,
     *     name: string,
     *     tlds: list<array{
     *         tld: string,
     *         register_minor: int,
     *         renew_minor: int,
     *         transfer_minor: int,
     *         term_years?: int,
     *         currency?: string,
     *     }>,
     * }>
     */
    private const REGISTRARS = [
        [
            'code' => 'cloudflare',
            'name' => 'Cloudflare',
            'tlds' => [
                [
                    'tld' => 'com',
                    'register_minor' => 13_00,
                    'renew_minor' => 13_00,
                    'transfer_minor' => 13_00,
                    'domain_cost_minor' => 9_00,
                ],
                [
                    'tld' => 'net',
                    'register_minor' => 14_00,
                    'renew_minor' => 14_00,
                    'transfer_minor' => 14_00,
                    'domain_cost_minor' => 10_25,
                ],
                [
                    'tld' => 'org',
                    'register_minor' => 13_00,
                    'renew_minor' => 13_00,
                    'transfer_minor' => 13_00,
                    'domain_cost_minor' => 9_00,
                ],
            ],
        ],
        [
            'code' => 'ovh',
            'name' => 'OVH',
            'tlds' => [
                [
                    'tld' => 'pt',
                    'register_minor' => 18_00,
                    'renew_minor' => 18_00,
                    'transfer_minor' => 0,
                    'domain_cost_minor' => 13_09,
                ],
                [
                    'tld' => 'com.pt',
                    'register_minor' => 18_00,
                    'renew_minor' => 18_00,
                    'transfer_minor' => 18_00,
                    'domain_cost_minor' => 13_09,
                ],
                [
                    'tld' => 'eu',
                    'register_minor' => 12_00,
                    'renew_minor' => 12_00,
                    'transfer_minor' => 12_00,
                    'domain_cost_minor' => 8_59,
                ],
                [
                    'tld' => 'de',
                    'register_minor' => 12_00,
                    'renew_minor' => 12_00,
                    'transfer_minor' => 12_00,
                    'domain_cost_minor' => 5_19,
                ],
                [
                    'tld' => 'es',
                    'register_minor' => 12_00,
                    'renew_minor' => 12_00,
                    'transfer_minor' => 12_00,
                    'domain_cost_minor' => 5_79,
                ],
                [
                    'tld' => 'fr',
                    'register_minor' => 12_00,
                    'renew_minor' => 12_00,
                    'transfer_minor' => 12_00,
                    'domain_cost_minor' => 7_79,
                ],
                [
                    'tld' => 'io',
                    'register_minor' => 65_00,
                    'renew_minor' => 65_00,
                    'transfer_minor' => 65_00,
                    'domain_cost_minor' => 59_79,
                ],

            ],
        ],
        [
            'code' => 'dominios',
            'name' => 'Dominios',
            'tlds' => [
                [
                    'tld' => 'edu.pt',
                    'register_minor' => 30_00,
                    'renew_minor' => 30_00,
                    'transfer_minor' => 0_00,
                    'domain_cost_minor' => 25_50,
                ],
            ],
        ],
        [
            'code' => 'netim',
            'name' => 'Netim',
            'tlds' => [
                [
                    'tld' => 'ma',
                    'register_minor' => 30_00,
                    'renew_minor' => 30_00,
                    'transfer_minor' => 30_00,
                    'domain_cost_minor' => 24_00,
                ],
            ],
        ],
    ];

    public function run(): void
    {
        BillingTaxSetting::instance();
        BillingAutomationSetting::instance();

        $this->seedProducts();

        Server::query()->firstOrCreate(
            ['hostname' => 'houston.hosted.pt'],
            [
                'name' => 'houston',
                'panel_url' => 'https://houston.hosted.pt:10000',
                'status' => ServerStatus::Active,
                'cost_amount_minor' => 28_67,
                'cost_currency' => 'EUR',
                'specs' => [
                    'cpu' => '4 vCPU',
                    'ram_gb' => 16,
                    'disk_gb' => 500,
                ],
            ],
        );

        Server::query()->firstOrCreate(
            ['hostname' => 'eagle.hosted.pt'],
            [
                'name' => 'eagle',
                'panel_url' => 'https://eagle.hosted.pt:10000',
                'status' => ServerStatus::Active,
                'cost_amount_minor' => 42_90,
                'cost_currency' => 'EUR',
                'specs' => [
                    'cpu' => '8 vCPU',
                    'ram_gb' => 32,
                    'disk_gb' => 800,
                ],
            ],
        );

        Server::query()->firstOrCreate(
            ['hostname' => 'cheetah.hosted.pt'],
            [
                'name' => 'cheetah',
                'panel_url' => 'https://cheetah.hosted.pt:10000',
                'status' => ServerStatus::Active,
                'cost_amount_minor' => 25_27,
                'cost_currency' => 'EUR',
                'specs' => [
                    'cpu' => '4 vCPU',
                    'ram_gb' => 32,
                    'disk_gb' => 500,
                ],
            ],
        );

        Server::query()->firstOrCreate(
            ['hostname' => 'leopard.hosted.pt'],
            [
                'name' => 'leopard',
                'panel_url' => 'https://leopard.hosted.pt:10000',
                'status' => ServerStatus::Active,
                'cost_amount_minor' => 25_27,
                'cost_currency' => 'EUR',
                'specs' => [
                    'cpu' => '4 vCPU',
                    'ram_gb' => 32,
                    'disk_gb' => 500,
                ],
            ],
        );

        Server::query()->firstOrCreate(
            ['hostname' => 'allms.hosted.pt'],
            [
                'name' => 'allms',
                'panel_url' => 'https://allms.hosted.pt:10000',
                'status' => ServerStatus::Active,
                'cost_amount_minor' => 41_87,
                'cost_currency' => 'EUR',
                'specs' => [
                    'cpu' => '12 vCPU',
                    'ram_gb' => 64,
                    'disk_gb' => 1000,
                ],
            ],
        );

        Server::query()->firstOrCreate(
            ['hostname' => 'nka.hosted.pt'],
            [
                'name' => 'nka',
                'status' => ServerStatus::Active,
                'cost_amount_minor' => 42_30,
                'cost_currency' => 'EUR',
                'specs' => [
                    'cpu' => '12 vCPU',
                    'ram_gb' => 64,
                    'disk_gb' => 512,
                ],
            ],
        );

        Server::query()->firstOrCreate(
            ['hostname' => 'windows.fmb.pt'],
            [
                'name' => 'WS25',
                'status' => ServerStatus::Active,
                'cost_amount_minor' => 25_05,
                'cost_currency' => 'EUR',
                'specs' => [
                    'cpu' => '6 vCPU',
                    'ram_gb' => 12,
                    'disk_gb' => 200,
                ],
            ],
        );

        $this->seedRegistrars();
    }

    private function seedRegistrars(): void
    {
        foreach (self::REGISTRARS as $registrarRow) {
            $registrar = Registrar::query()->updateOrCreate(
                ['code' => $registrarRow['code']],
                [
                    'name' => $registrarRow['name'],
                    'is_active' => true,
                ],
            );

            foreach ($registrarRow['tlds'] as $tldRow) {
                $normalizedTld = RegistrarTld::normalizeTld($tldRow['tld']);
                $existingId = RegistrarTld::findMatchingId($normalizedTld, (int) $registrar->id);

                $tld = $existingId !== null
                    ? RegistrarTld::query()->findOrFail($existingId)
                    : RegistrarTld::query()->create([
                        'registrar_id' => $registrar->id,
                        'tld' => $normalizedTld,
                    ]);

                $termYears = $tldRow['term_years'] ?? 1;
                $currency = $tldRow['currency'] ?? 'EUR';

                foreach (
                    [
                        [RegistrarTldPriceOperation::Register, 'register_minor'],
                        [RegistrarTldPriceOperation::Renew, 'renew_minor'],
                        [RegistrarTldPriceOperation::Transfer, 'transfer_minor'],
                    ] as [$operation, $amountKey]
                ) {
                    RegistrarTldPrice::query()->updateOrCreate(
                        [
                            'registrar_tld_id' => $tld->id,
                            'operation' => $operation,
                            'term_years' => $termYears,
                            'currency' => $currency,
                        ],
                        [
                            'amount_minor' => $tldRow[$amountKey],
                            'domain_cost_minor' => $tldRow['domain_cost_minor'] ?? null,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    private function seedProducts(): void
    {
        foreach (self::PRODUCTS as $row) {
            $product = Product::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'type' => $row['type'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'is_active' => true,
                    'default_allocated_disk_gb' => $row['disk_gb'],
                    'default_allocated_ram_gb' => $row['ram_gb'],
                ],
            );

            if (isset($row['month_minor'])) {
                ProductPrice::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'billing_kind' => ProductPriceBillingKind::Recurring,
                        'interval_unit' => IntervalUnit::Month,
                        'interval_count' => $row['month_count'] ?? 1,
                        'currency' => 'EUR',
                    ],
                    [
                        'amount_minor' => $row['month_minor'],
                        'is_active' => true,
                    ],
                );
            }

            if (isset($row['year_minor'])) {
                ProductPrice::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'billing_kind' => ProductPriceBillingKind::Recurring,
                        'interval_unit' => IntervalUnit::Year,
                        'interval_count' => 1,
                        'currency' => 'EUR',
                    ],
                    [
                        'amount_minor' => $row['year_minor'],
                        'is_active' => true,
                    ],
                );
            }

        }
    }
}
