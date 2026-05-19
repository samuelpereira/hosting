<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'whmcs_id', 'client_id', 'number', 'status', 'currency', 'subtotal_minor', 'tax_minor', 'irs_minor', 'total_minor',
        'issued_at', 'due_at', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    /** Services referenced on line items (excludes manual lines with no service). */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'order_items', 'order_id', 'service_id')
            ->distinct();
    }

    /**
     * Placeholders for order-related email templates.
     *
     * @return array<string, string>
     */
    public function emailVariables(?Transaction $transaction = null): array
    {
        $this->loadMissing(['client', 'items']);

        $paidAt = $transaction?->paid_at ?? $this->paid_at;

        return array_merge(BillingCompanySetting::instance()->emailVariables(), [
            'client_name' => (string) ($this->client?->name ?? ''),
            'client_email' => (string) ($this->client?->email ?? ''),
            'order_number' => (string) $this->number,
            'order_total' => number_format($this->total_minor / 100, 2, '.', ''),
            'order_currency' => (string) $this->currency,
            'order_issued_date' => $this->issued_at?->format('Y-m-d') ?? '',
            'order_due_date' => $this->due_at?->format('Y-m-d') ?? '',
            'paid_at' => $paidAt?->format('Y-m-d') ?? '',
            'order_lines' => $this->orderLinesHtml(),
        ]);
    }

    public function orderLinesHtml(): string
    {
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return '';
        }

        $currency = e((string) $this->currency);
        $items = $this->items->map(function (OrderItem $item) use ($currency) {
            $quantity = max(1, (int) $item->quantity);
            $lineMinor = (int) $item->unit_amount_minor * $quantity;
            $amount = number_format($lineMinor / 100, 2, '.', '');

            return '<li>'.e((string) $item->description).' - '.$amount.' '.$currency.'</li>';
        })->implode("\n");

        return "<ul>\n{$items}\n</ul>";
    }

    public function applyCompletedPayment(Transaction $transaction): void
    {
        if ($transaction->order_id !== $this->id || $transaction->status !== TransactionStatus::Completed) {
            return;
        }

        $this->update([
            'status' => OrderStatus::Paid,
            'paid_at' => $transaction->paid_at,
        ]);
    }

    public function syncPaymentStatusFromTransactions(): void
    {
        $completedQuery = $this->transactions()->where('status', TransactionStatus::Completed);
        $paidMinor = (int) $completedQuery->sum('amount_minor');

        if ($paidMinor >= $this->total_minor) {
            $latest = (clone $completedQuery)->orderByDesc('paid_at')->orderByDesc('id')->first();
            $this->update([
                'status' => OrderStatus::Paid,
                'paid_at' => $latest?->paid_at,
            ]);

            return;
        }

        if ($this->status === OrderStatus::Paid) {
            $this->update([
                'status' => OrderStatus::Unpaid,
                'paid_at' => null,
            ]);
        }
    }
}
