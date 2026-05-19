<?php

namespace App\Services\Whmcs;

use App\Enums\IntervalUnit;
use App\Enums\ProductPriceBillingKind;

final class WhmcsBillingCycle
{
    /**
     * @return array{0: ProductPriceBillingKind, 1: IntervalUnit, 2: int}
     */
    public static function parse(string $cycle): array
    {
        $normalized = strtolower(trim($cycle));

        return match ($normalized) {
            'one time', 'onetime' => [ProductPriceBillingKind::OneTime, IntervalUnit::Month, 1],
            'monthly' => [ProductPriceBillingKind::Recurring, IntervalUnit::Month, 1],
            'quarterly' => [ProductPriceBillingKind::Recurring, IntervalUnit::Month, 3],
            'semi-annually', 'semiannually', 'semi annually' => [ProductPriceBillingKind::Recurring, IntervalUnit::Month, 6],
            'annually', 'annual' => [ProductPriceBillingKind::Recurring, IntervalUnit::Year, 1],
            'biennially', 'biennial' => [ProductPriceBillingKind::Recurring, IntervalUnit::Year, 2],
            'triennially', 'triennial' => [ProductPriceBillingKind::Recurring, IntervalUnit::Year, 3],
            default => [ProductPriceBillingKind::Recurring, IntervalUnit::Month, 1],
        };
    }
}
