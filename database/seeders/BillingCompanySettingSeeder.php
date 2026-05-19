<?php

namespace Database\Seeders;

use App\Models\BillingCompanySetting;
use Illuminate\Database\Seeder;

class BillingCompanySettingSeeder extends Seeder
{
    /**
     * Default company details when env/config values are not set.
     *
     * @var array<string, string|null>
     */
    private const DEFAULTS = [
        'name' => null,
        'address_line' => null,
        'postal_code' => null,
        'city' => null,
        'country' => null,
        'phone' => null,
        'email' => null,
        'bank_account' => null,
    ];

    public function run(): void
    {
        $attributes = $this->resolvedAttributes();

        BillingCompanySetting::instance()->update(
            collect($attributes)
                ->map(fn (?string $value) => $value !== null && $value !== '' ? $value : null)
                ->all(),
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function resolvedAttributes(): array
    {
        $fromConfig = array_filter(
            config('billing.company', []),
            fn ($value) => $value !== null && $value !== '',
        );

        return array_merge(self::DEFAULTS, $fromConfig);
    }
}
