<section class="mx-auto w-full max-w-5xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Header, totals, and line items') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        @if ($order)
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Client') }}</flux:heading>
                <flux:card class="space-y-1 p-4">
                    <flux:link :href="route('billing.clients.show', $order->client_id)" wire:navigate class="font-medium">
                        {{ $order->client?->name }}
                    </flux:link>
                    @if ($order->client?->company_name)
                        <flux:text class="text-sm text-zinc-500">{{ $order->client->company_name }}</flux:text>
                    @endif
                    @if ($order->client?->vat_number)
                        <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('VAT') }}:</span> {{ $order->client->vat_number }}</flux:text>
                    @endif
                </flux:card>
            </div>
            <flux:input wire:model="number" :label="__('Number')" required />
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="client_id" :label="__('Client')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="number" :label="__('Number')" required />
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:select wire:model.live="status" :label="__('Status')">
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->name }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" />
            <flux:input wire:model="issued_at" type="date" :label="__('Issued')" />
            <flux:input wire:model="due_at" type="date" :label="__('Due')" />
        </div>
        <flux:input wire:model="paid_at" type="date" :label="__('Paid at')" />
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

        <div class="flex items-center justify-between">
            <flux:heading size="sm">{{ __('Line items') }}</flux:heading>
            <flux:button type="button" size="sm" variant="outline" wire:click="addLine" icon="plus">{{ __('Add line') }}</flux:button>
        </div>

        @foreach ($lineItems as $index => $line)
            <flux:card class="space-y-3 p-4" wire:key="line-{{ $index }}">
                <flux:select wire:model.live="lineItems.{{ $index }}.type" :label="__('Type')">
                    @foreach ($itemTypes as $it)
                        <option value="{{ $it->value }}">{{ $it->name }}</option>
                    @endforeach
                </flux:select>
                @if (($line['type'] ?? '') === 'service')
                    <flux:select wire:model="lineItems.{{ $index }}.service_id" :label="__('Service')">
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($services as $svc)
                            @php($d = $svc->associated_domain ?? $svc->domain?->fqdn)
                            <option value="{{ $svc->id }}">
                                {{ $svc->product?->name }}@if ($d) ({{ $d }})@endif #{{ $svc->id }}
                            </option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:input wire:model="lineItems.{{ $index }}.description" :label="__('Description')" />
                @if ($lineTaxBadges[$index] ?? null)
                    <flux:badge size="sm" :color="$lineTaxBadges[$index]['color']">{{ $lineTaxBadges[$index]['label'] }}</flux:badge>
                @endif
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:input wire:model.live="lineItems.{{ $index }}.quantity" type="number" min="1" :label="__('Qty')" />
                    <flux:input wire:model.live="lineItems.{{ $index }}.unit_amount_input" :label="__('Unit (major)')" />
                    <div class="flex items-end justify-end">
                        <flux:button type="button" size="sm" variant="ghost" wire:click="removeLine({{ $index }})">{{ __('Remove') }}</flux:button>
                    </div>
                </div>
            </flux:card>
        @endforeach

        <flux:card class="space-y-2 p-4">
            <flux:heading size="sm">{{ __('Totals') }}</flux:heading>
            <div class="flex justify-between text-sm">
                <flux:text class="text-zinc-500">{{ __('Subtotal') }}</flux:text>
                <flux:text class="font-medium">{{ $currency }} {{ number_format($orderTotals['subtotal_minor'] / 100, 2) }}</flux:text>
            </div>
            <div class="flex justify-between text-sm">
                <flux:text class="text-zinc-500">{{ $vatTotalsLabel }}</flux:text>
                <flux:text class="font-medium">{{ $currency }} {{ number_format($orderTotals['tax_minor'] / 100, 2) }}</flux:text>
            </div>
            <div class="flex justify-between text-sm">
                <flux:text class="text-zinc-500">{{ $irsTotalsLabel }}</flux:text>
                <flux:text class="font-medium text-red-600 dark:text-red-400">− {{ $currency }} {{ number_format($orderTotals['irs_minor'] / 100, 2) }}</flux:text>
            </div>
            <div class="flex justify-between border-t border-zinc-200 pt-2 text-sm dark:border-zinc-700">
                <flux:text class="font-medium text-zinc-800 dark:text-white">{{ __('Total') }}</flux:text>
                <flux:text class="font-semibold text-zinc-800 dark:text-white">{{ $currency }} {{ number_format($orderTotals['total_minor'] / 100, 2) }}</flux:text>
            </div>
        </flux:card>

        <div class="flex flex-wrap items-center gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('billing.orders.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
            @if ($order)
                <flux:button
                    type="button"
                    variant="danger"
                    class="ms-auto"
                    wire:click="deleteOrder"
                    wire:confirm="{{ __('Delete this order? All line items and payments will be removed. This cannot be undone.') }}"
                >{{ __('Delete order') }}</flux:button>
            @endif
        </div>
    </form>

    @if ($order)
        <flux:card class="mt-8 space-y-4 p-6">
            <div>
                <flux:heading size="sm">{{ __('Send email') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Notify the client using a billing email template.') }}
                    @if ($email_template_slug === 'order_paid' && $emailPayment?->hasDocument())
                        {{ __('The latest payment document will be attached.') }}
                    @endif
                </flux:text>
            </div>

            @if ($recipientEmail)
                <flux:text class="text-sm">
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('To') }}:</span>
                    {{ $recipientEmail }}
                </flux:text>
            @else
                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('This order’s client has no email address.') }}
                </flux:callout>
            @endif

            @if ($emailTemplates->isNotEmpty() && $recipientEmail)
                <flux:select wire:model.live="email_template_slug" :label="__('Template')">
                    @foreach ($emailTemplates as $emailTemplate)
                        <option value="{{ $emailTemplate->slug }}">{{ $emailTemplate->name }}</option>
                    @endforeach
                </flux:select>

                <flux:button
                    type="button"
                    variant="primary"
                    icon="paper-airplane"
                    wire:click="sendEmail"
                    wire:confirm="{{ __('Send this email to the client now?') }}"
                >{{ __('Send email') }}</flux:button>
            @elseif ($recipientEmail)
                <flux:text class="text-sm text-zinc-500">{{ __('No active order email templates. Add templates in Email templates.') }}</flux:text>
            @endif
        </flux:card>

        <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Payments') }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('Transactions linked to this order') }}</flux:subheading>
            </div>
            <flux:button
                variant="primary"
                :href="route('billing.transactions.create', ['order_id' => $order->id])"
                wire:navigate
                icon="plus"
            >{{ __('Record payment') }}</flux:button>
        </div>

        <flux:card class="mt-4 overflow-hidden p-0">
            <flux:table container:class="px-4 sm:px-6 py-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Method') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Paid at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Document') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($payments as $tx)
                        <flux:table.row :key="$tx->id">
                            <flux:table.cell>
                                <flux:link :href="route('billing.transactions.edit', $tx)" wire:navigate>{{ $tx->id }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $tx->method?->label() ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="center">{{ $tx->paid_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $tx->currency }} {{ number_format($tx->amount_minor / 100, 2) }}</flux:table.cell>
                            <flux:table.cell align="center">
                                @if ($tx->hasDocument())
                                    <flux:link
                                        :href="route('billing.transactions.document', $tx)"
                                        icon="paper-clip"
                                        class="text-sm"
                                    >{{ __('Download') }}</flux:link>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="center">
                                <flux:badge size="sm" :color="$tx->status->badgeColor()" class="capitalize">
                                    {{ $tx->status->value }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deletePayment({{ $tx->id }})"
                                    wire:confirm="{{ __('Delete this payment?') }}"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ __('No transactions found.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</section>
