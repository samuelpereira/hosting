<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => config('billing.user.name'),
            'email' => config('billing.user.email'),
            'password' => config('billing.user.password'),
        ]);

        $this->call(BillingSeeder::class);

        $this->call(BillingCompanySettingSeeder::class);

        $this->call(EmailTemplateSeeder::class);

        $this->call(WhmcsSeeder::class);
    }
}
