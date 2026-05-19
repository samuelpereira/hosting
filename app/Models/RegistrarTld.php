<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\RegistrarTldPriceOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrarTld extends Model
{
    protected $fillable = ['registrar_id', 'tld'];

    public static function normalizeTld(string $tld): string
    {
        return strtolower(ltrim(trim($tld), '.'));
    }

    /**
     * Find an existing TLD row by normalized label (same registrar first, then any registrar).
     */
    public static function findMatchingId(string $tld, ?int $registrarId = null): ?int
    {
        $normalized = self::normalizeTld($tld);

        if ($registrarId !== null) {
            foreach (self::query()->where('registrar_id', $registrarId)->get(['id', 'tld']) as $row) {
                if (self::normalizeTld($row->tld) === $normalized) {
                    return (int) $row->id;
                }
            }
        }

        foreach (self::query()->get(['id', 'registrar_id', 'tld']) as $row) {
            if (self::normalizeTld($row->tld) === $normalized) {
                return (int) $row->id;
            }
        }

        return null;
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RegistrarTldPrice::class);
    }

    /**
     * @return array{0: IntervalUnit, 1: int}
     */
    public function defaultBillingInterval(?RegistrarTldPriceOperation $preferOperation = null): array
    {
        if ($preferOperation !== null) {
            $price = $this->prices()
                ->where('is_active', true)
                ->where('operation', $preferOperation)
                ->orderBy('term_years')
                ->first();

            if ($price instanceof RegistrarTldPrice) {
                return $price->billingInterval();
            }
        }

        $price = $this->prices()
            ->where('is_active', true)
            ->orderBy('term_years')
            ->first();

        if ($price instanceof RegistrarTldPrice) {
            return $price->billingInterval();
        }

        return [IntervalUnit::Year, 1];
    }
}
