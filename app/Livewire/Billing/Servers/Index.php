<?php

namespace App\Livewire\Billing\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> ServerStatus values; empty = all statuses. Default: active only. */
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
        $allowed = array_map(fn (ServerStatus $s) => $s->value, ServerStatus::cases());
        $this->statuses = array_values(array_unique(array_intersect($allowed, $this->statuses)));
        $this->resetPage();
    }

    public function statusFilterSummary(): string
    {
        $allowed = array_map(fn (ServerStatus $s) => $s->value, ServerStatus::cases());
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

        $servers = Server::query()
            ->when($this->q !== '', fn ($q) => $q->where('name', 'like', $needle)->orWhere('hostname', 'like', $needle))
            ->when($this->statuses !== [], fn ($query) => $query->whereIn('status', $this->statuses))
            ->when($sortBy === 'id', fn ($query) => $query->orderBy('servers.id', $dir))
            ->when($sortBy === 'name', fn ($query) => $query->orderBy('name', $dir)->orderBy('id', 'asc'))
            ->paginate(15);

        $activeAlloc = Server::activeAllocationByServerIds($servers->pluck('id'));

        return view('livewire.billing.servers.index', [
            'servers' => $servers,
            'activeAlloc' => $activeAlloc,
        ])->title(__('Servers'));
    }
}
