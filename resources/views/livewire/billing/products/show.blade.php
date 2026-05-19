<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $product->name }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ $product->slug }}
                <flux:badge size="sm" :color="$product->is_active ? 'green' : 'amber'" class="ms-2">
                    {{ $product->is_active ? __('Active') : __('Inactive') }}
                </flux:badge>
                <flux:badge size="sm" class="ms-1 capitalize">{{ \Illuminate\Support\Str::headline($product->type->value) }}</flux:badge>
            </flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="outline" :href="route('billing.products.edit', $product)" wire:navigate icon="pencil">{{ __('Edit') }}</flux:button>
            <flux:button variant="primary" :href="route('billing.services.create', ['product_id' => $product->id])" wire:navigate icon="plus">{{ __('Service') }}</flux:button>
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
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Default RAM') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ \App\Models\Server::formatRamMb($product->capacityRamMb()) }}</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Pre-fill for new services') }}</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Default disk') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ \App\Models\Server::formatDiskGb($product->capacityDiskGb()) }}</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Pre-fill for new services') }}</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active prices') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ $activePricesCount }}</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Of :total price rows', ['total' => $product->prices->count()]) }}</flux:text>
        </flux:card>
    </div>

    <flux:card class="mt-4 p-6 space-y-4">
        <flux:heading size="sm">{{ __('Details') }}</flux:heading>
        @if ($product->description)
            <div>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</flux:text>
                <flux:text class="mt-1 whitespace-pre-wrap text-zinc-800 dark:text-white">{{ $product->description }}</flux:text>
            </div>
        @endif
        @if ($product->prices->isNotEmpty())
            <div>
                <flux:text class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Prices') }}</flux:text>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Billing') }}</flux:table.column>
                        <flux:table.column>{{ __('Interval') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                        <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($product->prices as $price)
                            <flux:table.row wire:key="price-{{ $price->id }}">
                                <flux:table.cell>{{ $price->billing_kind->name }}</flux:table.cell>
                                <flux:table.cell>{{ $price->interval_count }} {{ $price->interval_unit->name }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $price->currency }} {{ number_format($price->amount_minor / 100, 2) }}</flux:table.cell>
                                <flux:table.cell align="center">
                                    @if ($price->is_active)
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </flux:card>

    <flux:card class="mt-6 p-4">
        <flux:heading size="sm">{{ __('Services using this product') }}</flux:heading>
        <flux:text class="mt-0.5 text-xs text-zinc-500">{{ __('All subscriptions on this catalog item') }}</flux:text>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-9">
                <flux:input wire:model.live.debounce.300ms="q" :label="__('Search')" placeholder="{{ __('Client, domain, server') }}" />
            </div>
            <div class="space-y-2 sm:col-span-3">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Status') }}</span>
                <flux:dropdown position="bottom" align="end" class="w-full">
                    <flux:button variant="outline" class="w-full justify-between" icon-trailing="chevron-down">
                        {{ $this->statusFilterSummary() }}
                    </flux:button>
                    <flux:menu>
                        <div class="min-w-[12rem] space-y-1 p-2" wire:click.stop>
                            @foreach (\App\Enums\ServiceStatus::cases() as $serviceStatus)
                                <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                    <input
                                        type="checkbox"
                                        wire:model.live="statuses"
                                        value="{{ $serviceStatus->value }}"
                                        class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                                    />
                                    <span>{{ \Illuminate\Support\Str::headline($serviceStatus->value) }}</span>
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
                    <button type="button" wire:click="sort('client')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Client') }}
                        @if ($sortBy === 'client')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Server') }}</flux:table.column>
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
                <flux:table.column align="end">{{ __('RAM') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Disk') }}</flux:table.column>
                <flux:table.column align="end">
                    <button type="button" wire:click="sort('due')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Due at') }}
                        @if ($sortBy === 'due')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column align="end">
                    <button type="button" wire:click="sort('recurring')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Recurring') }}
                        @if ($sortBy === 'recurring')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($services as $service)
                    <flux:table.row wire:key="svc-{{ $service->id }}">
                        <flux:table.cell class="tabular-nums text-zinc-600 dark:text-zinc-400">{{ $service->id }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('billing.clients.show', $service->client_id)" wire:navigate>{{ $service->client?->firstAndLastName() }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($service->server)
                                <flux:link :href="route('billing.servers.show', $service->server)" wire:navigate>{{ $service->server->name }}</flux:link>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $service->associated_domain ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ \App\Models\Server::formatRamMb($service->allocated_ram_mb) }}</flux:table.cell>
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
                        <flux:table.cell colspan="9">{{ __('No services match your filters.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($services->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $services->links() }}</div>
        @endif
    </flux:card>
</section>
