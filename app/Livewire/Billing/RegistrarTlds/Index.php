<?php

namespace App\Livewire\Billing\RegistrarTlds;

use App\Models\RegistrarTld;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->q).'%';
        $tlds = RegistrarTld::query()
            ->with([
                'registrar',
                'prices' => fn ($q) => $q->orderBy('operation')->orderBy('term_years'),
            ])
            ->when($this->q !== '', fn ($q) => $q->where('tld', 'like', $needle)
                ->orWhereHas('registrar', fn ($r) => $r->where('name', 'like', $needle)))
            ->orderBy('tld')
            ->paginate(20);

        return view('livewire.billing.registrar-tlds.index', compact('tlds'))->title(__('Registrar TLDs'));
    }
}
