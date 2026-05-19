<?php

namespace App\Models;

use App\Enums\TransactionMethod;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $fillable = [
        'order_id', 'status', 'method', 'currency', 'amount_minor', 'paid_at', 'reference', 'notes',
        'document_path', 'document_name',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Transaction $transaction): void {
            $transaction->deleteDocumentFile();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'method' => TransactionMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Short unique payment reference (e.g. 1P54RO). */
    public static function generateReference(): string
    {
        do {
            $reference = strtoupper(Str::random(6));
        } while ($reference === '' || static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function hasDocument(): bool
    {
        return $this->document_path !== null
            && Storage::disk('local')->exists($this->document_path);
    }

    public function deleteDocumentFile(): void
    {
        if ($this->document_path === null) {
            return;
        }

        Storage::disk('local')->delete($this->document_path);
    }
}
