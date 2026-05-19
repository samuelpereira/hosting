<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Servers') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Infrastructure nodes') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('billing.servers.create')" wire:navigate icon="plus">{{ __('Add server') }}</flux:button>
    </div>

    <flux:card class="mt-6 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-9">
                <flux:input wire:model.live.debounce.300ms="q" :label="__('Search')" placeholder="{{ __('Name, hostname') }}" />
            </div>
            <div class="space-y-2 sm:col-span-3">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Status') }}</span>
                <flux:dropdown position="bottom" align="end" class="w-full">
                    <flux:button variant="outline" class="w-full justify-between" icon-trailing="chevron-down">
                        {{ $this->statusFilterSummary() }}
                    </flux:button>
                    <flux:menu>
                        <div class="min-w-[12rem] space-y-1 p-2" wire:click.stop>
                            @foreach (\App\Enums\ServerStatus::cases() as $serverStatus)
                                <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                    <input
                                        type="checkbox"
                                        wire:model.live="statuses"
                                        value="{{ $serverStatus->value }}"
                                        class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                                    />
                                    <span>{{ \Illuminate\Support\Str::headline($serverStatus->value) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>
    </flux:card>

    <flux:card class="mt-4 overflow-hidden p-0">
        <flux:table container:class="px-4 sm:px-6 py-3">
            <flux:table.columns>
                <flux:table.column>
                    <button type="button" wire:click="sort('id')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('ID') }}
                        @if ($sortBy === 'id')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sort('name')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Name') }}
                        @if ($sortBy === 'name')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Hostname') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Capacity') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Active services') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($servers as $server)
                    @php
                        $a = $activeAlloc->get($server->id) ?? ['ram_gb' => 0, 'disk_gb' => 0];
                        $ramUsed = $a['ram_gb'];
                        $diskUsed = $a['disk_gb'];
                        $ramCap = $server->capacityRamGb();
                        $diskCap = $server->capacityDiskGb();
                        $ramPct = $ramCap !== null && $ramCap > 0 ? min(100, round(100 * $ramUsed / $ramCap, 1)) : null;
                        $diskPct = $diskCap !== null && $diskCap > 0 ? min(100, round(100 * $diskUsed / $diskCap, 1)) : null;
                    @endphp
                    <flux:table.row :key="$server->id">
                        <flux:table.cell>{{ $server->id }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('billing.servers.show', $server)" wire:navigate>{{ $server->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $server->hostname }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-normal text-sm">
                            <div>{{ \App\Models\Server::formatRamGb($ramCap) }}</div>
                            <div class="text-zinc-500">{{ \App\Models\Server::formatDiskGb($diskCap) }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-normal text-sm">
                            <div>
                                {{ \App\Models\Server::formatRamGb($ramUsed) }}
                                @if ($ramPct !== null)
                                    <span class="text-zinc-500">· {{ $ramPct }}%</span>
                                @elseif ($ramCap === null && $ramUsed > 0)
                                    <span class="text-zinc-500">{{ __('(no RAM capacity)') }}</span>
                                @endif
                            </div>
                            <div>
                                {{ \App\Models\Server::formatDiskGb($diskUsed) }}
                                @if ($diskPct !== null)
                                    <span class="text-zinc-500">· {{ $diskPct }}%</span>
                                @elseif ($diskCap === null && $diskUsed > 0)
                                    <span class="text-zinc-500">{{ __('(no disk capacity)') }}</span>
                                @endif
                            </div>
                            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Allocated by services in active status') }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:badge size="sm" :color="$server->status->badgeColor()" class="capitalize">
                                {{ \Illuminate\Support\Str::headline($server->status->value) }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">{{ __('No servers found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($servers->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $servers->links() }}</div>
        @endif
    </flux:card>
</section>
