<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Service;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $unpaidOrders = Order::query()
            ->with('client')
            ->where('status', OrderStatus::Draft)
            ->orWhere('status', OrderStatus::Unpaid)
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('livewire.dashboard', [
            'statClients' => Client::query()->where('is_active', true)->count(),
            'statServices' => Service::query()->count(),
            'statDomains' => Domain::query()->count(),
            'statOpenOrders' => Order::query()->whereIn('status', [
                OrderStatus::Draft->value,
                OrderStatus::Unpaid->value,
            ])->count(),
            'unpaidOrders' => $unpaidOrders,
        ]);
    }
}
