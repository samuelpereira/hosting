<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Transactions') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Payments linked to orders') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('billing.transactions.create')" wire:navigate icon="plus">{{ __('Add transaction') }}</flux:button>
    </div>

    <flux:card class="mt-6 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-9">
                <flux:input wire:model.live.debounce.300ms="q" :label="__('Search reference, method, order #')" />
            </div>
            <div class="space-y-2 sm:col-span-3">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Status') }}</span>
                <flux:dropdown position="bottom" align="end" class="w-full">
                    <flux:button variant="outline" class="w-full justify-between" icon-trailing="chevron-down">
                        {{ $this->statusFilterSummary() }}
                    </flux:button>
                    <flux:menu>
                        <div class="min-w-[12rem] space-y-1 p-2" wire:click.stop>
                            @foreach (\App\Enums\TransactionStatus::cases() as $transactionStatus)
                                <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                    <input
                                        type="checkbox"
                                        wire:model.live="statuses"
                                        value="{{ $transactionStatus->value }}"
                                        class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                                    />
                                    <span>{{ \Illuminate\Support\Str::headline($transactionStatus->value) }}</span>
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
                <flux:table.column>{{ __('ID') }}</flux:table.column>
                <flux:table.column>{{ __('Order') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Paid at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transactions as $tx)
                    <flux:table.row :key="$tx->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('billing.transactions.edit', $tx)" wire:navigate>{{ $tx->id }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            @if ($tx->order)
                                <flux:link :href="route('billing.orders.edit', $tx->order)" wire:navigate>{{ $tx->order->number }}</flux:link>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('billing.clients.show', $tx->order->client_id)" wire:navigate>{{ $tx->order->client?->firstAndLastName() }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $tx->method?->label() ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $tx->paid_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $tx->currency }} {{ number_format($tx->amount_minor / 100, 2) }}</flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:badge size="sm" :color="$tx->status->badgeColor()" class="capitalize">
                                {{ $tx->status->value }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">{{ __('No transactions found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($transactions->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $transactions->links() }}</div>
        @endif
    </flux:card>
</section>
