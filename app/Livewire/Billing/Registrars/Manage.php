<?php

namespace App\Livewire\Billing\Registrars;

use App\Models\Registrar;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public ?Registrar $registrar = null;

    public string $name = '';

    public string $code = '';

    public string $notes = '';

    public bool $is_active = true;

    public function mount(?Registrar $registrar = null): void
    {
        $this->registrar = $registrar;
        if ($registrar) {
            $data = $registrar->only(['name', 'code', 'notes', 'is_active']);
            $this->fill(collect($data)->map(function ($value, string $key) {
                return $key === 'is_active' ? (bool) $value : ($value ?? '');
            })->all());
        }
    }

    public function save(): void
    {
        $codeRule = ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]*$/'];
        $codeRule[] = Rule::unique('registrars', 'code')->ignore($this->registrar?->id);
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => $codeRule,
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $payload = [
            'name' => $this->name,
            'code' => $this->code,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'is_active' => $this->is_active,
        ];

        if ($this->registrar) {
            $this->registrar->update($payload);
            Flux::toast(variant: 'success', text: __('Registrar updated.'));
            $this->redirect(route('billing.registrars.edit', $this->registrar), navigate: true);

            return;
        }
        $registrar = Registrar::query()->create($payload);
        Flux::toast(variant: 'success', text: __('Registrar created.'));
        $this->redirect(route('billing.registrars.edit', $registrar), navigate: true);
    }

    public function render()
    {
        $title = $this->registrar ? __('Edit registrar') : __('New registrar');

        return view('livewire.billing.registrars.manage', compact('title'))->title($title);
    }
}
