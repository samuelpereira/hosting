<?php

namespace App\Livewire\Billing\Registrars;

use App\Models\Registrar;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> Active / inactive filter; empty = all registrars. Default: active only. */
    public array $statuses = ['active'];

    #[Url(as: 'sort')]
    public string $sortBy = 'name';

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

        return __('All registrars');
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['id', 'name'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'name' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $sortBy = in_array($this->sortBy, ['id', 'name'], true) ? $this->sortBy : 'id';
        $dir = in_array($this->sortDir, ['asc', 'desc'], true) ? $this->sortDir : 'desc';

        $registrars = Registrar::query()
            ->when($this->q !== '', fn ($q) => $q->where('name', 'like', $needle)->orWhere('code', 'like', $needle))
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
            ->when($sortBy === 'id', fn ($query) => $query->orderBy('registrars.id', $dir))
            ->when($sortBy === 'name', fn ($query) => $query->orderBy('name', $dir)->orderBy('id', 'asc'))
            ->paginate(15);

        return view('livewire.billing.registrars.index', compact('registrars'))->title(__('Registrars'));
    }
}
