<?php

namespace App\Livewire\Billing\Products;

use App\Models\Product;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> Active / inactive filter; empty = all products. Default: active only. */
    public array $statuses = ['active'];

    #[Url(as: 'sort')]
    public string $sortBy = 'sort_order';

    #[Url(as: 'dir')]
    public string $sortDir = 'asc';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatuses(): void
    {
        $allowed = ['active', 'inactive'];
        $this->statuses = array_values(array_unique(array_intersect($allowed, $this->statuses)));
        $this->resetPage();
    }

    public function statusFilterSummary(): string
    {
        $active = in_array('active', $this->statuses, true);
        $inactive = in_array('inactive', $this->statuses, true);

        if ($active && $inactive) {
            return __('Active & inactive');
        }
        if ($active) {
            return __('Active');
        }
        if ($inactive) {
            return __('Inactive');
        }

        return __('All products');
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['id', 'name', 'sort_order'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = in_array($column, ['name', 'sort_order'], true) ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $sortBy = in_array($this->sortBy, ['id', 'name', 'sort_order'], true) ? $this->sortBy : 'sort_order';
        $dir = in_array($this->sortDir, ['asc', 'desc'], true) ? $this->sortDir : 'asc';

        $products = Product::query()
            ->when($this->q !== '', fn ($q) => $q->where('name', 'like', $needle)->orWhere('slug', 'like', $needle))
            ->when($this->statuses !== [], function ($query) {
                $query->where(function ($q) {
                    if (in_array('active', $this->statuses, true)) {
                        $q->orWhere('is_active', true);
                    }
                    if (in_array('inactive', $this->statuses, true)) {
                        $q->orWhere('is_active', false);
                    }
                });
            })
            ->when($sortBy === 'id', fn ($query) => $query->orderBy('products.id', $dir))
            ->when($sortBy === 'name', fn ($query) => $query->orderBy('name', $dir)->orderBy('sort_order', 'asc'))
            ->when($sortBy === 'sort_order', fn ($query) => $query->orderBy('sort_order', $dir)->orderBy('name', 'asc'))
            ->paginate(15);

        return view('livewire.billing.products.index', compact('products'))->title(__('Products'));
    }
}
