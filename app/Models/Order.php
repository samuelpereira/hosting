<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Services\Billing\OrderLinkedAssetRenewal;
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
            'order_totals' => $this->orderTotalsHtml(),
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

            return '<tr><td>'.e((string) $item->description).' - '.$amount.' '.$currency.'</td></tr>';
        })->implode("\n");

        return '<table cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-top: 25px;">'.$items.'</table>';
    }

    public function orderTotalsHtml(): string
    {
        $currency = e((string) $this->currency);
        $formatMinor = fn (int $minor): string => number_format($minor / 100, 2, '.', '');

        $this->loadMissing('client');
        $settings = BillingTaxSetting::instance();
        $client = $this->client;
        $vatRate = $client
            ? $client->effectiveVatRatePercent()
            : (float) $settings->vat_rate_percent;
        $irsRate = $client
            ? $client->effectiveIrsRatePercent()
            : (float) $settings->irs_rate_percent;

        $subtotalLabel = e(__('Subtotal'));
        $vatLabel = e($this->formatTaxTotalsLabel('IVA', $vatRate));
        $irsLabel = e($this->formatTaxTotalsLabel('IRS', $irsRate));
        $totalLabel = e(__('Total'));

        $subtotal = $formatMinor((int) $this->subtotal_minor);
        $tax = $formatMinor((int) $this->tax_minor);
        $total = $formatMinor((int) $this->total_minor);

        $labelStyle = 'padding: 1px 0; vertical-align: top;';
        $valueStyle = 'padding: 1px 0 1px 56px; text-align: right; white-space: nowrap; vertical-align: top;';
        $totalLabelStyle = 'padding: 1px 0; vertical-align: top;';
        $totalValueStyle = 'padding: 1px 0 1px 56px; text-align: right; white-space: nowrap; vertical-align: top;';

        $rows = <<<HTML
<tr><td style="{$labelStyle}">{$subtotalLabel}</td><td style="{$valueStyle}">{$subtotal} {$currency}</td></tr>
<tr><td style="{$labelStyle}">{$vatLabel}</td><td style="{$valueStyle}">{$tax} {$currency}</td></tr>
HTML;

        if ((int) $this->irs_minor > 0) {
            $irs = $formatMinor((int) $this->irs_minor);
            $rows .= <<<HTML
<tr><td style="{$labelStyle}">{$irsLabel}</td><td style="{$valueStyle}">− {$irs} {$currency}</td></tr>
HTML;
        }

        $rows .= <<<HTML
<tr style="border-top: 1px solid #ccc;"><td style="{$totalLabelStyle}"><strong>{$totalLabel}</strong></td><td style="{$totalValueStyle}"><strong>{$total} {$currency}</strong></td></tr>
HTML;

        return <<<HTML
<table cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-top: 15px; margin-bottom: 25px;">
{$rows}
</table>
HTML;
    }

    private function formatTaxTotalsLabel(string $name, float $ratePercent): string
    {
        if ($ratePercent <= 0) {
            return (string) __($name);
        }

        $rate = rtrim(rtrim(number_format($ratePercent, 2, '.', ''), '0'), '.');

        return (string) __(':name (:rate%)', [
            'name' => __($name),
            'rate' => $rate,
        ]);
    }

    public function applyCompletedPayment(Transaction $transaction): void
    {
        if ($transaction->order_id !== $this->id || $transaction->status !== TransactionStatus::Completed) {
            return;
        }

        $markingPaid = $this->status !== OrderStatus::Paid;

        $this->update([
            'status' => OrderStatus::Paid,
            'paid_at' => $transaction->paid_at,
        ]);

        if ($markingPaid) {
            $this->extendLinkedServicesAndDomains();
        }
    }

    /** Extend due dates on services and domains linked to this order's line items. */
    public function extendLinkedServicesAndDomains(): void
    {
        (new OrderLinkedAssetRenewal($this))->apply();
    }

    public function syncPaymentStatusFromTransactions(): void
    {
        $completedQuery = $this->transactions()->where('status', TransactionStatus::Completed);
        $paidMinor = (int) $completedQuery->sum('amount_minor');

        if ($paidMinor >= $this->total_minor) {
            $markingPaid = $this->status !== OrderStatus::Paid;
            $latest = (clone $completedQuery)->orderByDesc('paid_at')->orderByDesc('id')->first();
            $this->update([
                'status' => OrderStatus::Paid,
                'paid_at' => $latest?->paid_at,
            ]);

            if ($markingPaid) {
                $this->extendLinkedServicesAndDomains();
            }

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
