<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Services') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Subscriptions and allocations') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('billing.services.create')" wire:navigate icon="plus">{{ __('Add service') }}</flux:button>
    </div>

    <flux:card class="mt-6 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-9">
                <flux:input wire:model.live.debounce.300ms="q" :label="__('Search')" placeholder="{{ __('Product, client') }}"/>
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
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sort('product')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Product') }}
                        @if ($sortBy === 'product')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
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
                    <flux:table.row :key="$service->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('billing.services.edit', $service)" icon="eye" wire:navigate>{{ $service->id }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $service->associated_domain }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('billing.clients.show', $service->client_id)" wire:navigate>{{ $service->client?->firstAndLastName() }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($service->product)
                                <flux:link :href="route('billing.products.show', $service->product)" wire:navigate>{{ $service->product->name }}</flux:link>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $service->due_at?->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($service->recurring_amount_minor !== null)
                                {{ number_format($service->recurring_amount_minor / 100, 2) }} {{ $service->currency }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:badge size="sm" :color="$service->status->badgeColor()" class="capitalize">
                                {{ $service->status->value }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">{{ __('No services found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($services->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $services->links() }}</div>
        @endif
    </flux:card>
</section>
