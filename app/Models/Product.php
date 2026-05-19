<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'whmcs_id', 'type', 'name', 'slug', 'description',
        'default_allocated_ram_gb', 'default_allocated_disk_gb',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['type' => ProductType::class, 'is_active' => 'boolean'];
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
}
