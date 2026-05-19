<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Domains') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Registered names') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('billing.domains.create')" wire:navigate icon="plus">{{ __('Add domain') }}</flux:button>
    </div>

    <flux:card class="mt-6 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-9">
                <flux:input wire:model.live.debounce.300ms="q" :label="__('Search')" placeholder="{{ __('Domain') }}" />
            </div>
            <div class="space-y-2 sm:col-span-3">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Status') }}</span>
                <flux:dropdown position="bottom" align="end" class="w-full">
                    <flux:button variant="outline" class="w-full justify-between" icon-trailing="chevron-down">
                        {{ $this->statusFilterSummary() }}
                    </flux:button>
                    <flux:menu>
                        <div class="max-h-64 min-w-[12rem] space-y-1 overflow-y-auto p-2" wire:click.stop>
                            @foreach (\App\Enums\DomainStatus::cases() as $domainStatus)
                                <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                    <input
                                        type="checkbox"
                                        wire:model.live="statuses"
                                        value="{{ $domainStatus->value }}"
                                        class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                                    />
                                    <span>{{ \Illuminate\Support\Str::headline($domainStatus->value) }}</span>
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
                    <button type="button" wire:click="sort('domain')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Domain') }}
                        @if ($sortBy === 'domain')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column align="end">
                    <button type="button" wire:click="sort('due')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Due at') }}
                        @if ($sortBy === 'due')
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column align="end">{{ __('Recurring') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($domains as $domain)
                    <flux:table.row :key="$domain->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('billing.domains.edit', $domain)" wire:navigate>{{ $domain->id }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('billing.domains.edit', $domain)" wire:navigate>{{ $domain->fqdn }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('billing.clients.show', $domain->client_id)" wire:navigate>{{ $domain->client?->firstAndLastName() }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $domain->due_at?->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($domain->recurring_amount_minor !== null)
                                {{ number_format($domain->recurring_amount_minor / 100, 2) }} {{ $domain->currency }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:badge size="sm" :color="$domain->status->badgeColor()">
                                {{ \Illuminate\Support\Str::headline($domain->status->value) }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">{{ __('No domains found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($domains->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $domains->links() }}</div>
        @endif
    </flux:card>
</section>
