<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class WhmcsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Artisan::call('billing:import-whmcs-clients', [
            '--with-contacts' => true,
        ]);

        Artisan::call('billing:import-whmcs-hosting', [
            '--sync-products' => true,
        ]);

        Artisan::call('billing:import-whmcs-domains');

        Artisan::call('billing:import-whmcs-orders', [
            '--statuses' => 'Paid,Unpaid',
        ]);
    }
}
