<?php

namespace App\Livewire\Billing\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public ?Server $server = null;

    public string $name = '';

    public string $hostname = '';

    public string $panel_url = '';

    public string $status = 'active';

    public bool $allow_overallocation = false;

    public string $cost_amount_input = '0.00';

    public string $cost_currency = 'USD';

    public ?int $ram_gb = null;

    public ?int $disk_gb = null;

    public string $cpu = '';

    public string $notes = '';

    public function mount(?Server $server = null): void
    {
        $this->server = $server;
        if ($server) {
            $data = $server->only(['name', 'hostname', 'panel_url', 'notes']);
            $this->fill(collect($data)->map(fn ($value) => $value ?? '')->all());
            $this->status = $server->status->value;
            $this->allow_overallocation = $server->allowsOverallocation();
            $this->cost_currency = $server->cost_currency;
            $this->cost_amount_input = number_format($server->cost_amount_minor / 100, 2, '.', '');
            $specs = $server->specs ?? [];
            $this->cpu = (string) ($specs['cpu'] ?? '');
            if (isset($specs['ram_gb'])) {
                $this->ram_gb = (int) $specs['ram_gb'];
            } elseif (isset($specs['total_ram_mb'])) {
                $this->ram_gb = (int) round((int) $specs['total_ram_mb'] / 1024);
            }
            if (isset($specs['disk_gb'])) {
                $this->disk_gb = (int) $specs['disk_gb'];
            } elseif (isset($specs['total_disk_gb'])) {
                $this->disk_gb = (int) $specs['total_disk_gb'];
            }
        }
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255'],
            'panel_url' => ['nullable', 'string', 'url', 'max:255'],
            'status' => ['required', Rule::enum(ServerStatus::class)],
            'allow_overallocation' => ['boolean'],
            'cost_amount_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'cost_currency' => ['required', 'string', 'size:3'],
            'ram_gb' => ['nullable', 'integer', 'min:0'],
            'disk_gb' => ['nullable', 'integer', 'min:0'],
            'cpu' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
        if ($this->server) {
            $rules['hostname'][] = 'unique:servers,hostname,'.$this->server->id;
        } else {
            $rules['hostname'][] = 'unique:servers,hostname';
        }
        $this->validate($rules);

        $specs = array_filter([
            'cpu' => $this->cpu !== '' ? $this->cpu : null,
            'ram_gb' => $this->ram_gb,
            'disk_gb' => $this->disk_gb,
        ], fn ($v) => $v !== null && $v !== '');

        $payload = [
            'name' => $this->name,
            'hostname' => $this->hostname,
            'panel_url' => trim($this->panel_url) !== '' ? trim($this->panel_url) : null,
            'status' => ServerStatus::from($this->status),
            'allow_overallocation' => $this->allow_overallocation,
            'cost_amount_minor' => (int) round((float) $this->cost_amount_input * 100),
            'cost_currency' => strtoupper($this->cost_currency),
            'specs' => $specs !== [] ? $specs : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        if ($this->server) {
            $this->server->update($payload);
            Flux::toast(variant: 'success', text: __('Server updated.'));
            $this->redirect(route('billing.servers.show', $this->server), navigate: true);

            return;
        }
        $server = Server::query()->create($payload);
        Flux::toast(variant: 'success', text: __('Server created.'));
        $this->redirect(route('billing.servers.show', $server), navigate: true);
    }

    public function render()
    {
        $title = $this->server ? __('Edit server') : __('New server');

        return view('livewire.billing.servers.manage', [
            'title' => $title,
            'statuses' => ServerStatus::cases(),
        ])->title($title);
    }
}
