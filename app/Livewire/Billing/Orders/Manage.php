<?php

namespace App\Livewire\Billing\Orders;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Models\BillingTaxSetting;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Billing\BillingEmailSender;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public ?Order $order = null;

    public ?int $client_id = null;

    public string $number = '';

    public string $status = 'draft';

    public string $currency = 'EUR';

    public ?string $issued_at = null;

    public ?string $due_at = null;

    public ?string $paid_at = null;

    public string $notes = '';

    /** @var array<int, array{id: ?int, type: string, description: string, quantity: int, unit_amount_input: string, tax_amount_input: string, service_id: ?int}> */
    public array $lineItems = [];

    public string $email_template_slug = 'order_created';

    public function mount(?Order $order = null): void
    {
        $this->order = $order;
        if ($order) {
            $order->load(['items', 'client']);
            $this->client_id = $order->client_id;
            $this->number = $order->number;
            $this->status = $order->status->value;
            $this->currency = $order->currency;
            $this->issued_at = $order->issued_at?->format('Y-m-d');
            $this->due_at = $order->due_at?->format('Y-m-d');
            $this->paid_at = $order->paid_at?->format('Y-m-d');
            $this->notes = (string) ($order->notes ?? '');
            $this->lineItems = $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'type' => $item->type->value,
                'description' => $item->description,
                'quantity' => (int) $item->quantity,
                'unit_amount_input' => number_format($item->unit_amount_minor / 100, 2, '.', ''),
                'tax_amount_input' => number_format($item->tax_amount_minor / 100, 2, '.', ''),
                'service_id' => $item->service_id,
            ])->values()->all();
        } else {
            $this->number = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            $this->issued_at = now()->format('Y-m-d');

            $sid = request()->integer('service_id');
            if ($sid > 0) {
                $service = Service::query()->with(['product', 'domain'])->find($sid);
                if ($service) {
                    $this->applyServicePrefill($service);
                } else {
                    $this->applyClientIdFromRequest();
                    $this->resetLineItemsToDefaultManual();
                }
            } else {
                $this->applyClientIdFromRequest();
                $this->resetLineItemsToDefaultManual();
            }
        }
        if ($this->lineItems === []) {
            $this->addLine();
        }

        $this->recalculateAllLineTaxes();
        $this->ensureEmailTemplateSlugIsValid();
    }

    public function updated($property): void
    {
        if ($property === 'client_id') {
            $this->recalculateAllLineTaxes();

            return;
        }

        if (preg_match('/^lineItems\.(\d+)\.(unit_amount_input|quantity)$/', $property, $matches)) {
            $this->recalculateLineTax((int) $matches[1]);
        }
    }

    public function updatedStatus(): void
    {
        $this->ensureEmailTemplateSlugIsValid();
    }

    private function applyClientIdFromRequest(): void
    {
        $cid = request()->integer('client_id');
        $this->client_id = $cid > 0 ? $cid : null;
    }

    private function resetLineItemsToDefaultManual(): void
    {
        $this->lineItems = [[
            'id' => null,
            'type' => OrderItemType::Manual->value,
            'description' => '',
            'quantity' => 1,
            'unit_amount_input' => '0.00',
            'tax_amount_input' => '0.00',
            'service_id' => null,
        ]];
    }

    private function applyServicePrefill(Service $service): void
    {
        $this->client_id = $service->client_id;
        $currency = $service->currency
            ?? Client::query()->whereKey($service->client_id)->value('currency')
            ?? 'USD';
        $this->currency = is_string($currency) ? strtoupper($currency) : 'USD';

        $description = $service->renewalOrderLineDescription();

        $amountMinor = $service->recurring_amount_minor ?? $service->first_payment_amount_minor;
        $unitMajor = $amountMinor !== null
            ? number_format($amountMinor / 100, 2, '.', '')
            : '0.00';

        $this->lineItems = [[
            'id' => null,
            'type' => OrderItemType::Service->value,
            'description' => $description,
            'quantity' => 1,
            'unit_amount_input' => $unitMajor,
            'tax_amount_input' => '0.00',
            'service_id' => $service->id,
        ]];
    }

    public function addLine(): void
    {
        $this->lineItems[] = [
            'id' => null,
            'type' => OrderItemType::Manual->value,
            'description' => '',
            'quantity' => 1,
            'unit_amount_input' => '0.00',
            'tax_amount_input' => '0.00',
            'service_id' => null,
        ];
        $this->recalculateLineTax(array_key_last($this->lineItems));
    }

    public function removeLine(int $index): void
    {
        unset($this->lineItems[$index]);
        $this->lineItems = array_values($this->lineItems);
        if ($this->lineItems === []) {
            $this->addLine();
        }
    }

    public function save(): void
    {
        foreach (array_keys($this->lineItems) as $i) {
            if (($this->lineItems[$i]['type'] ?? '') !== OrderItemType::Service->value) {
                $this->lineItems[$i]['service_id'] = null;
            } elseif ($this->lineItems[$i]['service_id'] === '' || $this->lineItems[$i]['service_id'] === 0) {
                $this->lineItems[$i]['service_id'] = null;
            } else {
                $this->lineItems[$i]['service_id'] = (int) $this->lineItems[$i]['service_id'];
            }
        }

        $this->client_id = (int) $this->client_id;

        $numberRule = ['required', 'string', 'max:64'];
        $numberRule[] = Rule::unique('orders', 'number')->ignore($this->order?->id);

        $rules = [
            'number' => $numberRule,
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'currency' => ['required', 'string', 'size:3'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lineItems' => ['required', 'array', 'min:1'],
            'lineItems.*.type' => ['required', Rule::enum(OrderItemType::class)],
            'lineItems.*.description' => ['required', 'string', 'max:65535'],
            'lineItems.*.quantity' => ['required', 'integer', 'min:1'],
            'lineItems.*.unit_amount_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lineItems.*.tax_amount_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lineItems.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];

        if ($this->order === null) {
            $rules['client_id'] = ['required', 'exists:clients,id'];
        }

        $this->recalculateAllLineTaxes();
        $this->validate($rules);

        foreach ($this->lineItems as $i => $row) {
            if ($row['type'] === OrderItemType::Service->value && empty($row['service_id'])) {
                $this->addError('lineItems.'.$i.'.service_id', __('Service line requires a service.'));

                return;
            }
            if (! empty($row['service_id'])) {
                $svc = Service::query()->find($row['service_id']);
                if (! $svc || (int) $svc->client_id !== (int) $this->client_id) {
                    $this->addError('lineItems.'.$i.'.service_id', __('Service must belong to this client.'));

                    return;
                }
            }
        }

        $amounts = $this->computeOrderAmounts();

        $order = DB::transaction(function () use ($amounts) {
            $payload = [
                'client_id' => $this->client_id,
                'number' => $this->number,
                'status' => OrderStatus::from($this->status),
                'currency' => strtoupper($this->currency),
                'subtotal_minor' => $amounts['subtotal_minor'],
                'tax_minor' => $amounts['tax_minor'],
                'irs_minor' => $amounts['irs_minor'],
                'total_minor' => $amounts['total_minor'],
                'issued_at' => $this->issued_at,
                'due_at' => $this->due_at,
                'paid_at' => $this->paid_at,
                'notes' => $this->notes !== '' ? $this->notes : null,
            ];
            if ($this->order) {
                unset($payload['client_id']);
                $this->order->update($payload);
                $order = $this->order->fresh(['client']);
            } else {
                $order = Order::query()->create($payload);
            }
            $order->items()->delete();
            foreach ($this->lineItems as $pos => $row) {
                $unitMinor = (int) round((float) $row['unit_amount_input'] * 100);
                $taxMinor = (int) round((float) $row['tax_amount_input'] * 100);
                $order->items()->create([
                    'position' => $pos,
                    'type' => OrderItemType::from($row['type']),
                    'description' => $row['description'],
                    'quantity' => (int) $row['quantity'],
                    'unit_amount_minor' => $unitMinor,
                    'tax_amount_minor' => $taxMinor,
                    'service_id' => $row['service_id'] ?: null,
                ]);
            }

            return $order;
        });

        Flux::toast(variant: 'success', text: __('Order saved.'));
        $this->redirect(route('billing.orders.edit', $order), navigate: true);
    }

    public function deletePayment(int $transactionId): void
    {
        if ($this->order === null) {
            return;
        }

        $transaction = Transaction::query()
            ->where('order_id', $this->order->id)
            ->whereKey($transactionId)
            ->firstOrFail();

        $transaction->delete();

        $this->order->refresh();
        $this->order->syncPaymentStatusFromTransactions();
        $this->order->refresh();

        $this->status = $this->order->status->value;
        $this->paid_at = $this->order->paid_at?->format('Y-m-d');

        Flux::toast(variant: 'success', text: __('Payment deleted.'));
    }

    public function deleteOrder(): void
    {
        if ($this->order === null) {
            return;
        }

        DB::transaction(function () {
            $this->order->transactions()->each(fn (Transaction $transaction) => $transaction->delete());
            $this->order->delete();
        });

        Flux::toast(variant: 'success', text: __('Order deleted.'));

        $this->redirect(route('billing.orders.index'), navigate: true);
    }

    public function sendEmail(BillingEmailSender $sender): void
    {
        if ($this->order === null) {
            return;
        }

        $allowedSlugs = $this->availableEmailTemplateSlugs();

        $this->validate([
            'email_template_slug' => [
                'required',
                'string',
                Rule::in($allowedSlugs),
            ],
        ]);

        $order = $this->order->loadMissing('client');

        if ($this->email_template_slug === 'order_paid' && ! $this->orderIsPaid()) {
            Flux::toast(variant: 'danger', text: __('The order paid template is only available for paid orders.'));

            return;
        }

        $recipients = $order->client?->billingEmailRecipients() ?? [];
        if ($recipients === []) {
            Flux::toast(variant: 'danger', text: __('This client has no email address.'));

            return;
        }

        $clientEmail = $recipients[0];

        $template = EmailTemplate::query()
            ->where('slug', $this->email_template_slug)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            Flux::toast(variant: 'danger', text: __('Email template not found or inactive.'));

            return;
        }

        $transaction = $this->email_template_slug === 'order_paid'
            ? $this->latestCompletedPayment()
            : null;

        $sender->sendToClient(
            $template,
            $clientEmail,
            $order->emailVariables($transaction),
            $order->client_id,
            $transaction,
            $order->id,
        );

        Flux::toast(variant: 'success', text: __('Email queued to :recipients.', [
            'recipients' => implode(', ', $recipients),
        ]));
    }

    /**
     * @return list<string>
     */
    private function availableEmailTemplateSlugs(): array
    {
        return array_values(array_filter(
            BillingEmailSender::ORDER_TEMPLATE_SLUGS,
            fn (string $slug) => $slug !== 'order_paid' || $this->orderIsPaid(),
        ));
    }

    private function orderIsPaid(): bool
    {
        return OrderStatus::tryFrom($this->status) === OrderStatus::Paid;
    }

    private function ensureEmailTemplateSlugIsValid(): void
    {
        $slugs = $this->availableEmailTemplateSlugs();
        if ($slugs === []) {
            return;
        }

        if (! in_array($this->email_template_slug, $slugs, true)) {
            $this->email_template_slug = $slugs[0];
        }
    }

    private function latestCompletedPayment(): ?Transaction
    {
        if ($this->order === null) {
            return null;
        }

        return $this->order->transactions()
            ->where('status', TransactionStatus::Completed)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();
    }

    private function recalculateAllLineTaxes(): void
    {
        foreach (array_keys($this->lineItems) as $index) {
            $this->recalculateLineTax($index);
        }
    }

    private function recalculateLineTax(int $index): void
    {
        if (! isset($this->lineItems[$index])) {
            return;
        }

        $client = $this->clientForTax();
        if ($client === null) {
            $this->lineItems[$index]['tax_amount_input'] = '0.00';

            return;
        }

        $row = $this->lineItems[$index];
        $unitMinor = (int) round((float) ($row['unit_amount_input'] ?? 0) * 100);
        $quantity = max(1, (int) ($row['quantity'] ?? 1));
        $lineSubtotalMinor = $quantity * $unitMinor;
        $vatMinor = BillingTaxSetting::instance()->vatMinorForLine($client, $lineSubtotalMinor);

        $this->lineItems[$index]['tax_amount_input'] = number_format($vatMinor / 100, 2, '.', '');
    }

    /**
     * @return array{subtotal_minor: int, tax_minor: int, irs_minor: int, total_minor: int}
     */
    private function computeOrderAmounts(): array
    {
        $client = $this->clientForTax();
        $settings = BillingTaxSetting::instance();

        $subtotalMinor = 0;
        $taxMinor = 0;
        $irsMinor = 0;

        foreach ($this->lineItems as $row) {
            $unitMinor = (int) round((float) ($row['unit_amount_input'] ?? 0) * 100);
            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $lineSubtotalMinor = $quantity * $unitMinor;
            $subtotalMinor += $lineSubtotalMinor;

            if ($client !== null) {
                $isDomainLine = ($row['type'] ?? '') === OrderItemType::Domain->value;
                $taxMinor += $settings->vatMinorForLine($client, $lineSubtotalMinor);
                $irsMinor += $settings->irsMinorForLine($client, $lineSubtotalMinor, $isDomainLine);
            }
        }

        return [
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'irs_minor' => $irsMinor,
            'total_minor' => $subtotalMinor + $taxMinor - $irsMinor,
        ];
    }

    private function clientForTax(): ?Client
    {
        $clientId = (int) $this->client_id;
        if ($clientId <= 0) {
            return null;
        }

        return Client::query()->find($clientId);
    }

    private function formatRatePercent(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    private function formatTaxTotalsLabel(string $name, float $ratePercent): string
    {
        if ($ratePercent <= 0) {
            return __($name);
        }

        return __(':name (:rate%)', [
            'name' => __($name),
            'rate' => $this->formatRatePercent($ratePercent),
        ]);
    }

    /**
     * @param  array{type?: string}  $row
     * @return array{label: string, color: string}|null
     */
    private function lineTaxBadge(?Client $client, array $row): ?array
    {
        if ($client === null) {
            return null;
        }

        $settings = BillingTaxSetting::instance();
        $isDomainLine = ($row['type'] ?? '') === OrderItemType::Domain->value;

        $hasVat = $settings->vatRateForClient($client) > 0;
        $hasIrs = $settings->irsRateForClient($client, $isDomainLine) > 0;

        if (! $hasVat && ! $hasIrs) {
            return ['label' => __('No tax'), 'color' => 'green'];
        }

        if ($hasVat && $hasIrs) {
            return ['label' => __('VAT + IRS'), 'color' => 'amber'];
        }

        if ($hasVat) {
            return ['label' => __('VAT only'), 'color' => 'amber'];
        }

        return ['label' => __('IRS only'), 'color' => 'amber'];
    }

    public function render()
    {
        $title = $this->order ? __('Edit order') : __('New order');
        $client = $this->client_id ? Client::query()->find($this->client_id) : null;
        $services = $client
            ? Service::query()->where('client_id', $client->id)->with(['product', 'domain'])->orderBy('id')->get()
            : collect();

        $orderTotals = $this->computeOrderAmounts();
        $settings = BillingTaxSetting::instance();
        $vatRatePercent = $client
            ? $client->effectiveVatRatePercent()
            : (float) $settings->vat_rate_percent;
        $irsRatePercent = $client
            ? $client->effectiveIrsRatePercent()
            : (float) $settings->irs_rate_percent;

        $lineTaxBadges = [];
        foreach ($this->lineItems as $index => $row) {
            $lineTaxBadges[$index] = $this->lineTaxBadge($client, $row);
        }

        return view('livewire.billing.orders.manage', [
            'title' => $title,
            'orderTotals' => $orderTotals,
            'lineTaxBadges' => $lineTaxBadges,
            'vatTotalsLabel' => $this->formatTaxTotalsLabel('VAT', $vatRatePercent),
            'irsTotalsLabel' => $this->formatTaxTotalsLabel('IRS', $irsRatePercent),
            'clients' => $this->order === null
                ? Client::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'statuses' => OrderStatus::cases(),
            'itemTypes' => OrderItemType::cases(),
            'services' => $services,
            'payments' => $this->order
                ? $this->order->transactions()->orderByDesc('id')->get()
                : collect(),
            'recipientEmail' => $this->order?->client?->email,
            'emailTemplates' => $this->order
                ? EmailTemplate::query()
                    ->whereIn('slug', $this->availableEmailTemplateSlugs())
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                : collect(),
            'emailPayment' => $this->latestCompletedPayment(),
        ])->title($title);
    }
}
