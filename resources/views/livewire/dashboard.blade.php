<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-8 lg:px-8">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Billing overview and shortcuts') }}</flux:subheading>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active clients') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $statClients }}</flux:heading>
                <flux:link :href="route('billing.clients.index')" wire:navigate class="mt-2 inline-block text-sm">
                    {{ __('View clients') }}
                </flux:link>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Services') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $statServices }}</flux:heading>
                <flux:link :href="route('billing.services.index')" wire:navigate class="mt-2 inline-block text-sm">
                    {{ __('View services') }}
                </flux:link>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Domains') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $statDomains }}</flux:heading>
                <flux:link :href="route('billing.domains.index')" wire:navigate class="mt-2 inline-block text-sm">
                    {{ __('View domains') }}
                </flux:link>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open orders') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $statOpenOrders }}</flux:heading>
                <flux:link :href="route('billing.orders.index')" wire:navigate class="mt-2 inline-block text-sm">
                    {{ __('View orders') }}
                </flux:link>
            </flux:card>
        </div>

        <flux:card class="mt-10 overflow-hidden p-0 sm:mt-12">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Open orders') }}</flux:heading>
                <flux:button size="sm" :href="route('billing.orders.index')" wire:navigate variant="ghost">
                    {{ __('View all') }}
                </flux:button>
            </div>
            <div class="overflow-x-auto">
                <flux:table container:class="px-4 sm:px-6 py-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('ID') }}</flux:table.column>
                        <flux:table.column>{{ __('Client') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Issued at') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Due at') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                        <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($unpaidOrders as $order)
                            <flux:table.row wire:key="ord-{{ $order->id }}">
                                <flux:table.cell>
                                    <flux:link :href="route('billing.orders.edit', $order)" wire:navigate>
                                        {{ $order->id }}
                                    </flux:link>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:link :href="route('billing.clients.show', $order->client_id)" wire:navigate>{{ $order->client?->firstAndLastName() }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ $order->issued_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $order->due_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    {{ $order->currency }}
                                    {{ number_format($order->total_minor / 100, 2) }}
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    @if ($order->status instanceof \App\Enums\OrderStatus)
                                        <flux:badge size="sm" :color="$order->status->badgeColor()" class="capitalize">
                                            {{ $order->status->value }}
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" inset>
                                            {{ $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4">
                                    <flux:text class="text-zinc-500">{{ __('No unpaid orders.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    </div>
</section>
