<?php

namespace App\Livewire\Billing\Servers;

use App\Enums\ServiceStatus;
use App\Models\Server;
use Livewire\Component;

class Show extends Component
{
    public Server $server;

    public function mount(Server $server): void
    {
        $this->server = $server;
    }

    public function render()
    {
        $services = $this->server->services()
            ->with(['client', 'product'])
            ->orderByDesc('id')
            ->get();

        $servicesActive = $services->where('status', ServiceStatus::Active)->count();

        return view('livewire.billing.servers.show', [
            'services' => $services,
            'servicesTotal' => $services->count(),
            'servicesActive' => $servicesActive,
        ])->title($this->server->name);
    }
}
