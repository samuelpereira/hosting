<?php

namespace App\Livewire\Billing\Clients;

use App\Models\Client;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clients')]
class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> Active / inactive filter; empty = all clients. Default: active only. */
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

        return __('All clients');
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['id', 'name', 'registered_at', 'status'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = in_array($column, ['name'], true) ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        $clients = Client::query()
            ->when($this->q !== '', function ($query) use ($needle) {
                $query->where(function ($q) use ($needle) {
                    $q->where('name', 'like', $needle)
                        ->orWhere('company_name', 'like', $needle)
                        ->orWhere('email', 'like', $needle)
                        ->when(ctype_digit(trim($this->q)), fn ($q) => $q->orWhere('id', (int) trim($this->q)));
                });
            })
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
            ->when($this->sortBy === 'id', fn ($query) => $query->orderBy('id', $dir))
            ->when($this->sortBy === 'name', fn ($query) => $query->orderBy('name', $dir)->orderBy('id', 'asc'))
            ->when($this->sortBy === 'registered_at', fn ($query) => $query->orderBy('registered_at', $dir)->orderBy('id', 'desc'))
            ->when($this->sortBy === 'status', fn ($query) => $query->orderBy('is_active', $dir)->orderBy('name', 'asc'))
            ->paginate(15);

        return view('livewire.billing.clients.index', compact('clients'));
    }
}
