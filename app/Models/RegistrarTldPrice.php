<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\RegistrarTldPriceOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrarTldPrice extends Model
{
    protected $fillable = [
        'registrar_tld_id', 'operation', 'term_years', 'amount_minor', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return ['operation' => RegistrarTldPriceOperation::class, 'is_active' => 'boolean'];
    }

    public function registrarTld(): BelongsTo
    {
        return $this->belongsTo(RegistrarTld::class);
    }

    /**
     * @return array{0: IntervalUnit, 1: int}
     */
    public function billingInterval(): array
    {
        return [IntervalUnit::Year, max(1, (int) $this->term_years)];
    }
}
