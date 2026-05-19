<?php

namespace App\Livewire\Billing\Products;

use App\Enums\ServiceStatus;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public Product $product;

    public string $q = '';

    /** @var list<string> ServiceStatus values; empty = all statuses. Default: active only. */
    public array $statuses = ['active'];

    public string $sortBy = 'id';

    public string $sortDir = 'desc';

    public function mount(Product $product): void
    {
        $this->product = $product->load([
            'prices' => fn ($q) => $q->orderBy('billing_kind')->orderBy('interval_unit')->orderBy('interval_count'),
        ]);
    }

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
        if (! in_array($column, ['id', 'client', 'due', 'recurring'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = match ($column) {
                'client', 'due' => 'asc',
                'recurring' => 'desc',
                default => 'desc',
            };
        }

        $this->resetPage();
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $sortBy = in_array($this->sortBy, ['id', 'client', 'due', 'recurring'], true) ? $this->sortBy : 'id';
        $dir = in_array($this->sortDir, ['asc', 'desc'], true) ? $this->sortDir : 'desc';

        $services = $this->product->services()
            ->with(['client', 'server'])
            ->when($this->q !== '', function ($query) use ($needle) {
                $query->where(function ($query) use ($needle) {
                    $query->where('associated_domain', 'like', $needle)
                        ->orWhereHas('client', fn ($q) => $q->where('name', 'like', $needle)->orWhere('email', 'like', $needle))
                        ->orWhereHas('server', fn ($q) => $q->where('name', 'like', $needle)->orWhere('hostname', 'like', $needle));
                });
            })
            ->when($this->statuses !== [], fn ($query) => $query->whereIn('status', $this->statuses))
            ->when($sortBy === 'id', fn ($query) => $query->orderBy('services.id', $dir))
            ->when($sortBy === 'client', function ($query) use ($dir) {
                $query->leftJoin('clients', 'services.client_id', '=', 'clients.id')
                    ->select('services.*')
                    ->orderBy('clients.name', $dir)
                    ->orderBy('services.id', 'asc');
            })
            ->when($sortBy === 'due', fn ($query) => $query->orderBy('services.due_at', $dir)->orderBy('services.id', 'asc'))
            ->when($sortBy === 'recurring', fn ($query) => $query->orderBy('services.recurring_amount_minor', $dir)->orderBy('services.id', 'asc'))
            ->paginate(15);

        $servicesTotal = $this->product->services()->count();
        $servicesActive = $this->product->services()->where('status', ServiceStatus::Active)->count();
        $activePricesCount = $this->product->prices->where('is_active', true)->count();

        return view('livewire.billing.products.show', [
            'services' => $services,
            'servicesTotal' => $servicesTotal,
            'servicesActive' => $servicesActive,
            'activePricesCount' => $activePricesCount,
        ])->title($this->product->name);
    }
}
