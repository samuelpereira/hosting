<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\ProductPriceBillingKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id', 'billing_kind', 'interval_unit', 'interval_count',
        'amount_minor', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'billing_kind' => ProductPriceBillingKind::class,
            'interval_unit' => IntervalUnit::class,
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Recurring price normalized to a monthly amount (minor units). */
    public function monthlyAmountMinor(): int
    {
        if ($this->billing_kind === ProductPriceBillingKind::OneTime) {
            return 0;
        }

        $months = match ($this->interval_unit) {
            IntervalUnit::Month => max(1, (int) $this->interval_count),
            IntervalUnit::Year => max(1, (int) $this->interval_count) * 12,
        };

        return (int) round((int) $this->amount_minor / $months);
    }

    /** Recurring price normalized to a yearly amount (minor units). */
    public function yearlyAmountMinor(): int
    {
        return $this->monthlyAmountMinor() * 12;
    }
}
