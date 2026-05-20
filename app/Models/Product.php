<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\ProductPriceBillingKind;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'whmcs_id', 'type', 'name', 'slug', 'description',
        'default_allocated_ram_gb', 'default_allocated_disk_gb',
        'is_active', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Default listing order (internal catalog and public landing). */
    public function scopeOrderedForDisplay($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function nextSortOrder(): int
    {
        return ((int) static::query()->max('sort_order')) + 10;
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function capacityRamGb(): ?int
    {
        return $this->default_allocated_ram_gb;
    }

    public function capacityDiskGb(): ?int
    {
        return $this->default_allocated_disk_gb;
    }

    /** Best price to highlight on the public landing (recurring monthly first). */
    public function headlinePrice(): ?ProductPrice
    {
        $prices = $this->relationLoaded('prices')
            ? $this->prices->where('is_active', true)
            : $this->prices()->where('is_active', true)->get();

        return $prices
            ->sortBy([
                fn (ProductPrice $p) => $p->billing_kind === ProductPriceBillingKind::Recurring ? 0 : 1,
                fn (ProductPrice $p) => $p->interval_unit === IntervalUnit::Month ? 0 : 1,
                'amount_minor',
            ])
            ->first();
    }
}
