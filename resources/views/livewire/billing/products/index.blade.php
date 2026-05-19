<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Products') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Catalog and pricing') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('billing.products.create')" wire:navigate icon="plus">{{ __('Add product') }}</flux:button>
    </div>

    <flux:card class="mt-6 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-9">
                <flux:input wire:model.live.debounce.300ms="q" :label="__('Search')" placeholder="{{ __('Name, slug') }}" />
            </div>
            <div class="space-y-2 sm:col-span-3">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Status') }}</span>
                <flux:dropdown position="bottom" align="end" class="w-full">
                    <flux:button variant="outline" class="w-full justify-between" icon-trailing="chevron-down">
                        {{ $this->statusFilterSummary() }}
                    </flux:button>
                    <flux:menu>
                        <div class="min-w-[12rem] space-y-1 p-2" wire:click.stop>
                            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                <input
                                    type="checkbox"
                                    wire:model.live="statuses"
                                    value="active"
                                    class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                                />
                                <span>{{ __('Active') }}</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                <input
                                    type="checkbox"
                                    wire:model.live="statuses"
                                    value="inactive"
                                    class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                                />
                                <span>{{ __('Inactive') }}</span>
                            </label>
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
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Slug') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Capacity') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell class="tabular-nums text-zinc-600 dark:text-zinc-400">{{ $product->id }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('billing.products.show', $product)" wire:navigate>{{ $product->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $product->type->value }}</flux:table.cell>
                        <flux:table.cell>{{ $product->slug }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-normal text-sm">
                            <div>{{ \App\Models\Server::formatRamMb($product->capacityRamMb()) }}</div>
                            <div class="text-zinc-500">{{ \App\Models\Server::formatDiskGb($product->capacityDiskGb()) }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            @if ($product->is_active)
                                <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">{{ __('No products found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($products->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $products->links() }}</div>
        @endif
    </flux:card>
</section>
