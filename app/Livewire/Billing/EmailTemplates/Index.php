<?php

namespace App\Livewire\Billing\EmailTemplates;

use App\Models\EmailTemplate;
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
        $templates = EmailTemplate::query()
            ->when($this->q !== '', fn ($q) => $q->where(function ($q) use ($needle) {
                $q->where('name', 'like', $needle)
                    ->orWhere('slug', 'like', $needle)
                    ->orWhere('subject', 'like', $needle);
            }))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.billing.email-templates.index', compact('templates'))
            ->title(__('Email templates'));
    }
}
