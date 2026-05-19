<?php

namespace App\Livewire\Billing\Transactions;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> TransactionStatus values; empty = all. Default: completed only. */
    public array $statuses = [];

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatuses(): void
    {
        $allowed = array_map(fn (TransactionStatus $s) => $s->value, TransactionStatus::cases());
        $this->statuses = array_values(array_unique(array_intersect($allowed, $this->statuses)));
        $this->resetPage();
    }

    public function statusFilterSummary(): string
    {
        $allowed = array_map(fn (TransactionStatus $s) => $s->value, TransactionStatus::cases());
        sort($allowed);
        $selected = array_values(array_intersect($allowed, $this->statuses));
        sort($selected);

        if ($selected === [] || $selected === $allowed) {
            return __('All statuses');
        }

        if (count($selected) === 1) {
            return Str::headline($selected[0]);
        }

        return collect($selected)->map(fn (string $s) => Str::headline($s))->implode(', ');
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $transactions = Transaction::query()
            ->with('order')
            ->when($this->statuses !== [], fn ($query) => $query->whereIn('status', $this->statuses))
            ->when($this->q !== '', function ($query) use ($needle) {
                $query->where(function ($q) use ($needle) {
                    $q->where('reference', 'like', $needle)
                        ->orWhere('method', 'like', $needle)
                        ->orWhereHas('order', fn ($o) => $o->where('number', 'like', $needle));
                });
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.billing.transactions.index', compact('transactions'))->title(__('Transactions'));
    }
}
