<?php

namespace App\Livewire\Billing\Domains;

use App\Enums\DomainStatus;
use App\Models\Domain;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    /** @var list<string> DomainStatus values; empty = all statuses. Default: active only. */
    public array $statuses = ['active'];

    #[Url(as: 'sort')]
    public string $sortBy = 'domain';

    #[Url(as: 'dir')]
    public string $sortDir = 'asc';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatuses(): void
    {
        $allowed = array_map(fn (DomainStatus $s) => $s->value, DomainStatus::cases());
        $this->statuses = array_values(array_unique(array_intersect($allowed, $this->statuses)));
        $this->resetPage();
    }

    public function statusFilterSummary(): string
    {
        $allowed = array_map(fn (DomainStatus $s) => $s->value, DomainStatus::cases());
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
        if (! in_array($column, ['id', 'domain', 'due'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = match ($column) {
                'domain', 'due' => 'asc',
                default => 'desc',
            };
        }

        $this->resetPage();
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $sortBy = in_array($this->sortBy, ['id', 'domain', 'due'], true) ? $this->sortBy : 'domain';
        $dir = in_array($this->sortDir, ['asc', 'desc'], true) ? $this->sortDir : 'asc';

        $domains = Domain::query()
            ->with(['client', 'registrar'])
            ->when($this->q !== '', fn ($q) => $q->where('fqdn', 'like', $needle))
            ->when($this->statuses !== [], fn ($q) => $q->whereIn('status', $this->statuses))
            ->when($sortBy === 'id', fn ($q) => $q->orderBy('domains.id', $dir))
            ->when($sortBy === 'domain', fn ($q) => $q->orderBy('domains.fqdn', $dir)->orderBy('domains.id', 'asc'))
            ->when($sortBy === 'due', fn ($q) => $q->orderBy('domains.due_at', $dir)->orderBy('domains.id', 'asc'))
            ->paginate(20);

        return view('livewire.billing.domains.index', compact('domains'))->title(__('Domains'));
    }
}
