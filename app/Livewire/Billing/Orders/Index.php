<?php

namespace App\Livewire\Billing\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> OrderStatus values; empty = all. Default: paid and unpaid. */
    public array $statuses = ['paid', 'unpaid'];

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatuses(): void
    {
        $allowed = array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases());
        $this->statuses = array_values(array_unique(array_intersect($allowed, $this->statuses)));
        $this->resetPage();
    }

    public function statusFilterSummary(): string
    {
        $allowed = array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases());
        sort($allowed);
        $selected = array_values(array_intersect($allowed, $this->statuses));
        sort($selected);

        if ($selected === [] || $selected === $allowed) {
            return __('All statuses');
        }

        if ($selected === ['paid', 'unpaid']) {
            return __('Paid & unpaid');
        }

        if (count($selected) === 1) {
            return Str::headline($selected[0]);
        }

        return collect($selected)->map(fn (string $s) => Str::headline($s))->implode(', ');
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $orders = Order::query()
            ->with('client')
            ->when($this->q !== '', function ($query) use ($needle) {
                $query->where('number', 'like', $needle)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $needle));
            })
            ->when($this->statuses !== [], fn ($query) => $query->whereIn('status', $this->statuses))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.billing.orders.index', compact('orders'))->title(__('Orders'));
    }
}
