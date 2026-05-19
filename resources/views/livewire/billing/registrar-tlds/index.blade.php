<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Registrar TLDs') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('TLDs offered per registrar') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('billing.registrar-tlds.create')" wire:navigate icon="plus">{{ __('Add TLD') }}</flux:button>
    </div>

    <flux:card class="mt-6 p-4">
        <flux:input wire:model.live.debounce.300ms="q" :label="__('Search')" placeholder="{{ __('TLD, registrar') }}" />
    </flux:card>

    <flux:card class="mt-4 overflow-hidden p-0">
        <flux:table container:class="px-4 sm:px-6 py-3">
            <flux:table.columns>
                <flux:table.column>{{ __('ID') }}</flux:table.column>
                <flux:table.column>{{ __('TLD') }}</flux:table.column>
                <flux:table.column>{{ __('Registrar') }}</flux:table.column>
                <flux:table.column>{{ __('Prices') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($tlds as $tld)
                    <flux:table.row :key="$tld->id">
                        <flux:table.cell>{{ $tld->id }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('billing.registrar-tlds.edit', $tld)" wire:navigate>{{ ltrim($tld->tld, '.') }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $tld->registrar?->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($tld->prices->isEmpty())
                                <span class="text-sm text-zinc-500">{{ __('None') }}</span>
                            @else
                                <ul class="space-y-0.5 text-sm">
                                    @foreach ($tld->prices as $price)
                                        <li @class(['text-zinc-500' => ! $price->is_active])>
                                            {{ \Illuminate\Support\Str::headline($price->operation->value) }}
                                            {{ $price->term_years }}{{ __('yr') }}
                                            · {{ $price->currency }} {{ number_format($price->amount_minor / 100, 2) }}
                                            @if (! $price->is_active)
                                                <span class="text-xs">({{ __('inactive') }})</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">{{ __('No TLDs found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($tlds->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $tlds->links() }}</div>
        @endif
    </flux:card>
</section>
