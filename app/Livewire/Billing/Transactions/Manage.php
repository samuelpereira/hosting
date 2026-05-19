<?php

namespace App\Livewire\Billing\Transactions;

use App\Enums\TransactionMethod;
use App\Enums\TransactionStatus;
use App\Models\Order;
use App\Models\Transaction;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Manage extends Component
{
    use WithFileUploads;

    private const DOCUMENT_MIMES = 'pdf,jpg,jpeg,png,webp,gif,doc,docx';

    private const DOCUMENT_MAX_KB = 10240;
    public ?Transaction $transaction = null;

    public ?int $order_id = null;

    public string $status = 'completed';

    public string $method = 'banktransfer';

    public string $currency = 'EUR';

    public string $amount_input = '0.00';

    public ?string $paid_at = null;

    public string $reference = '';

    public string $notes = '';

    public bool $remove_document = false;

    /** @var TemporaryUploadedFile|null */
    public $document = null;

    public function mount(?Transaction $transaction = null): void
    {
        $this->transaction = $transaction?->load(['order.client']);
        if ($transaction) {
            $this->order_id = $transaction->order_id;
            $this->status = $transaction->status->value;
            $this->method = $transaction->method?->value ?? '';
            $this->currency = $transaction->currency;
            $this->amount_input = number_format($transaction->amount_minor / 100, 2, '.', '');
            $this->paid_at = $transaction->paid_at?->format('Y-m-d');
            $this->reference = (string) ($transaction->reference ?? '');
            $this->notes = (string) ($transaction->notes ?? '');
        } else {
            $this->reference = Transaction::generateReference();
            $this->paid_at = now()->format('Y-m-d');
            $oid = request()->integer('order_id');
            $this->order_id = $oid > 0 ? $oid : null;
            if ($this->order_id) {
                $order = Order::query()->find($this->order_id);
                if ($order) {
                    $this->applyDefaultsFromOrder($order);
                }
            }
        }
    }

    public function updatedOrderId(mixed $value): void
    {
        if ($this->transaction !== null) {
            return;
        }
        $oid = (int) $value;
        if ($oid <= 0) {
            return;
        }
        $order = Order::query()->find($oid);
        if ($order) {
            $this->applyDefaultsFromOrder($order);
        }
    }

    private function applyDefaultsFromOrder(Order $order): void
    {
        $this->currency = $order->currency;
        $this->paid_at = now()->format('Y-m-d');

        $paidMinor = (int) Transaction::query()
            ->where('order_id', $order->id)
            ->where('status', TransactionStatus::Completed)
            ->sum('amount_minor');
        $remaining = max(0, $order->total_minor - $paidMinor);
        $this->amount_input = number_format($remaining / 100, 2, '.', '');
    }

    public function save(): void
    {
        $this->order_id = (int) $this->order_id;

        $rules = [
            'status' => ['required', Rule::enum(TransactionStatus::class)],
            'method' => ['nullable', Rule::enum(TransactionMethod::class)],
            'currency' => ['required', 'string', 'size:3'],
            'amount_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'max:'.self::DOCUMENT_MAX_KB, 'mimes:'.self::DOCUMENT_MIMES],
        ];

        if ($this->transaction === null) {
            $rules['order_id'] = ['required', 'exists:orders,id'];
        }

        $this->validate($rules);

        $payload = [
            'order_id' => $this->order_id,
            'status' => TransactionStatus::from($this->status),
            'method' => $this->method !== '' ? TransactionMethod::from($this->method) : null,
            'currency' => strtoupper($this->currency),
            'amount_minor' => (int) round((float) $this->amount_input * 100),
            'paid_at' => $this->paid_at,
            'reference' => $this->reference !== '' ? $this->reference : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        if ($this->transaction) {
            unset($payload['order_id']);
            $this->transaction->update($payload);
            $this->syncDocument($this->transaction);
            $this->transaction->refresh();
            $this->transaction->order?->applyCompletedPayment($this->transaction);
            Flux::toast(variant: 'success', text: __('Transaction updated.'));
            $this->redirect(route('billing.transactions.edit', $this->transaction), navigate: true);

            return;
        }
        if (trim($this->reference) === '') {
            $this->reference = Transaction::generateReference();
        }

        $payload['reference'] = $this->reference;

        $tx = Transaction::query()->create($payload);
        $this->syncDocument($tx);
        $tx->order?->applyCompletedPayment($tx);
        Flux::toast(variant: 'success', text: __('Transaction created.'));
        $this->redirect(route('billing.transactions.edit', $tx), navigate: true);
    }

    public function removeDocument(): void
    {
        if ($this->transaction === null) {
            return;
        }

        $this->transaction->deleteDocumentFile();
        $this->transaction->update([
            'document_path' => null,
            'document_name' => null,
        ]);
        $this->transaction->refresh();
        $this->remove_document = false;
        $this->document = null;

        Flux::toast(variant: 'success', text: __('Document removed.'));
    }

    private function syncDocument(Transaction $transaction): void
    {
        if ($this->document instanceof UploadedFile) {
            $transaction->deleteDocumentFile();

            $originalName = $this->document->getClientOriginalName();
            $path = $this->document->store("transaction-documents/{$transaction->id}", 'local');

            $transaction->update([
                'document_path' => $path,
                'document_name' => $originalName !== '' ? $originalName : basename($path),
            ]);

            $this->document = null;
            $this->remove_document = false;

            return;
        }

        if ($this->remove_document) {
            $transaction->deleteDocumentFile();
            $transaction->update([
                'document_path' => null,
                'document_name' => null,
            ]);
            $this->remove_document = false;
        }
    }

    public function render()
    {
        $title = $this->transaction ? __('Edit transaction') : __('New transaction');

        return view('livewire.billing.transactions.manage', [
            'title' => $title,
            'order' => $this->transaction?->order,
            'orders' => $this->transaction === null
                ? Order::query()->with('client')->orderByDesc('id')->limit(200)->get()
                : collect(),
            'statuses' => TransactionStatus::cases(),
            'methods' => TransactionMethod::cases(),
        ])->title($title);
    }
}
