<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $server->name }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ $server->hostname }}
                <flux:badge size="sm" :color="$server->status->badgeColor()" class="ms-2 capitalize">
                    {{ \Illuminate\Support\Str::headline($server->status->value) }}
                </flux:badge>
            </flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="outline" :href="route('billing.servers.edit', $server)" wire:navigate icon="pencil">{{ __('Edit') }}</flux:button>
            <flux:button variant="primary" :href="route('billing.services.create', ['server_id' => $server->id])" wire:navigate icon="plus">{{ __('Service') }}</flux:button>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Services') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ $servicesTotal }} <span class="text-base font-normal text-zinc-500">/</span> {{ $servicesActive }}
            </flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Total · active status') }}</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('RAM (active services)') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ \App\Models\Server::formatRamGb($server->allocatedRamGbActiveServices()) }}
                @if ($server->capacityRamGb() !== null)
                    <span class="text-base font-normal text-zinc-500">/ {{ \App\Models\Server::formatRamGb($server->capacityRamGb()) }}</span>
                @endif
            </flux:heading>
            @if ($server->ramUsagePercentActive() !== null)
                <flux:text class="mt-1 text-xs text-zinc-500">{{ $server->ramUsagePercentActive() }}% {{ __('of capacity') }}</flux:text>
            @endif
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Disk (active services)') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ \App\Models\Server::formatDiskGb($server->allocatedDiskGbActiveServices()) }}
                @if ($server->capacityDiskGb() !== null)
                    <span class="text-base font-normal text-zinc-500">/ {{ \App\Models\Server::formatDiskGb($server->capacityDiskGb()) }}</span>
                @endif
            </flux:heading>
            @if ($server->diskUsagePercentActive() !== null)
                <flux:text class="mt-1 text-xs text-zinc-500">{{ $server->diskUsagePercentActive() }}% {{ __('of capacity') }}</flux:text>
            @endif
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monthly cost') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ $server->cost_currency }} {{ number_format($server->cost_amount_minor / 100, 2) }}
            </flux:heading>
        </flux:card>
    </div>

    <flux:card class="mt-4 p-6 space-y-4">
        <flux:heading size="sm">{{ __('Details') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Panel URL') }}</flux:text>
                <flux:text class="mt-1 font-medium text-zinc-800 dark:text-white">
                    @if ($server->panel_url)
                        <a href="{{ $server->panel_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $server->panel_url }}</a>
                    @else
                        —
                    @endif
                </flux:text>
            </div>
            @if (! empty($server->specs['cpu']))
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('CPU') }}</flux:text>
                    <flux:text class="mt-1 font-medium text-zinc-800 dark:text-white">{{ $server->specs['cpu'] }}</flux:text>
                </div>
            @endif
            <div>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Capacity (specs)') }}</flux:text>
                <flux:text class="mt-1 font-medium text-zinc-800 dark:text-white">
                    {{ \App\Models\Server::formatRamGb($server->capacityRamGb()) }}
                    · {{ \App\Models\Server::formatDiskGb($server->capacityDiskGb()) }}
                </flux:text>
                @if ($server->allowsOverallocation())
                    <flux:text class="mt-1 text-sm text-amber-600 dark:text-amber-400">{{ __('Allocations over capacity are allowed on this server.') }}</flux:text>
                @endif
            </div>
        </div>
        @if ($server->notes)
            <div>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Notes') }}</flux:text>
                <flux:text class="mt-1 whitespace-pre-wrap text-zinc-800 dark:text-white">{{ $server->notes }}</flux:text>
            </div>
        @endif
    </flux:card>

    <flux:card class="mt-6 overflow-hidden p-0">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Services on this server') }}</flux:heading>
            <flux:text class="mt-0.5 text-xs text-zinc-500">{{ __('All services assigned to this host') }}</flux:text>
        </div>
        <flux:table container:class="px-4 sm:px-6 py-3">
            <flux:table.columns>
                <flux:table.column>{{ __('ID') }}</flux:table.column>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
                <flux:table.column align="end">{{ __('RAM') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Disk') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Due at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Recurring') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($services as $service)
                    <flux:table.row wire:key="svc-{{ $service->id }}">
                        <flux:table.cell>
                            <flux:link :href="route('billing.services.edit', $service)" wire:navigate>{{ $service->id }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                            {{ $service->product?->name }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('billing.clients.show', $service->client_id)" wire:navigate>{{ $service->client?->firstAndLastName() }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $service->associated_domain ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ \App\Models\Server::formatRamGb($service->allocated_ram_gb) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ \App\Models\Server::formatDiskGb($service->allocated_disk_gb) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $service->due_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($service->recurring_amount_minor !== null)
                                {{ number_format($service->recurring_amount_minor / 100, 2) }} {{ $service->currency }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:badge size="sm" :color="$service->status->badgeColor()" class="capitalize">
                                {{ \Illuminate\Support\Str::headline($service->status->value) }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9">{{ __('No services on this server yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</section>
