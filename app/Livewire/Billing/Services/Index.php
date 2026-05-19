<?php

namespace App\Livewire\Billing\Services;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> ServiceStatus values; empty = all statuses. Default: active only. */
    public array $statuses = ['active'];

    #[Url(as: 'sort')]
    public string $sortBy = 'id';

    #[Url(as: 'dir')]
    public string $sortDir = 'desc';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatuses(): void
    {
        $allowed = array_map(fn (ServiceStatus $s) => $s->value, ServiceStatus::cases());
        $this->statuses = array_values(array_unique(array_intersect($allowed, $this->statuses)));
        $this->resetPage();
    }

    public function statusFilterSummary(): string
    {
        $allowed = array_map(fn (ServiceStatus $s) => $s->value, ServiceStatus::cases());
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

    public function sort(string $column): void
    {
        if (! in_array($column, ['id', 'product', 'due', 'recurring'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = match ($column) {
                'product', 'due' => 'asc',
                'recurring' => 'desc',
                default => 'desc',
            };
        }

        $this->resetPage();
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $sortBy = in_array($this->sortBy, ['id', 'product', 'due', 'recurring'], true) ? $this->sortBy : 'id';
        $dir = in_array($this->sortDir, ['asc', 'desc'], true) ? $this->sortDir : 'desc';

        $services = Service::query()
            ->with(['client', 'product', 'server'])
            ->when($this->q !== '', function ($query) use ($needle) {
                $query->whereHas('client', fn ($q) => $q->where('name', 'like', $needle)->orWhere('email', 'like', $needle))
                    ->orWhereHas('product', fn ($q) => $q->where('name', 'like', $needle));
            })
            ->when($this->statuses !== [], fn ($query) => $query->whereIn('status', $this->statuses))
            ->when($sortBy === 'id', fn ($query) => $query->orderBy('services.id', $dir))
            ->when($sortBy === 'product', function ($query) use ($dir) {
                $query->leftJoin('products', 'services.product_id', '=', 'products.id')
                    ->select('services.*')
                    ->orderBy('products.name', $dir)
                    ->orderBy('services.id', 'asc');
            })
            ->when($sortBy === 'due', fn ($query) => $query->orderBy('services.due_at', $dir)->orderBy('services.id', 'asc'))
            ->when($sortBy === 'recurring', fn ($query) => $query->orderBy('services.recurring_amount_minor', $dir)->orderBy('services.id', 'asc'))
            ->paginate(15);

        return view('livewire.billing.services.index', compact('services'))->title(__('Services'));
    }
}
