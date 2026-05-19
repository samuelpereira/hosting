<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $client->name }}</flux:heading>
            <flux:subheading class="mt-1">{{ $client->email }} · {{ $client->currency }}</flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="outline" :href="route('billing.clients.edit', $client)" wire:navigate icon="pencil">{{ __('Edit') }}</flux:button>
            <flux:button variant="primary" :href="route('billing.services.create', ['client_id' => $client->id])" wire:navigate icon="plus">{{ __('Service') }}</flux:button>
            <flux:button variant="primary" :href="route('billing.domains.create', ['client_id' => $client->id])" wire:navigate icon="plus">{{ __('Domain') }}</flux:button>
            <flux:button variant="primary" :href="route('billing.orders.create', ['client_id' => $client->id])" wire:navigate icon="plus">{{ __('Order') }}</flux:button>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
        <flux:button size="sm" :variant="$tab === 'overview' ? 'filled' : 'ghost'" wire:click="$set('tab', 'overview')">{{ __('Overview') }}</flux:button>
        <flux:button size="sm" :variant="$tab === 'contacts' ? 'filled' : 'ghost'" wire:click="$set('tab', 'contacts')">{{ __('Contacts') }}</flux:button>
        <flux:button size="sm" :variant="$tab === 'services' ? 'filled' : 'ghost'" wire:click="$set('tab', 'services')">{{ __('Services') }}</flux:button>
        <flux:button size="sm" :variant="$tab === 'domains' ? 'filled' : 'ghost'" wire:click="$set('tab', 'domains')">{{ __('Domains') }}</flux:button>
        <flux:button size="sm" :variant="$tab === 'orders' ? 'filled' : 'ghost'" wire:click="$set('tab', 'orders')">{{ __('Orders') }}</flux:button>
        <flux:button size="sm" :variant="$tab === 'emails' ? 'filled' : 'ghost'" wire:click="$set('tab', 'emails')">{{ __('Emails') }}</flux:button>
    </div>

    @if ($tab === 'overview')
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Products (via services)') }}</flux:text>
                <flux:heading size="lg" class="mt-1">
                    {{ $overview['products_total'] }} <span class="text-base font-normal text-zinc-500">/</span> {{ $overview['products_active'] }}
                </flux:heading>
                <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Distinct catalog products · still active in catalog') }}</flux:text>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Services') }}</flux:text>
                <flux:heading size="lg" class="mt-1">
                    {{ $overview['services_total'] }} <span class="text-base font-normal text-zinc-500">/</span> {{ $overview['services_active'] }}
                </flux:heading>
                <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Total · active status') }}</flux:text>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Domains') }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $overview['domains_total'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Payments received') }}</flux:text>
                <div class="mt-1 space-y-1">
                    @forelse ($overview['paid_totals'] as $pt)
                        <flux:heading size="lg">
                            {{ $pt['currency'] }} {{ number_format($pt['minor'] / 100, 2) }}
                        </flux:heading>
                    @empty
                        <flux:heading size="lg" class="text-zinc-400">—</flux:heading>
                    @endforelse
                </div>
                <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Completed transactions, by currency') }}</flux:text>
            </flux:card>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <flux:card class="overflow-hidden p-0">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Paid by month') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs text-zinc-500">{{ __('Active services · recurring price per month') }}</flux:text>
                </div>
                <flux:table container:class="px-4 sm:px-6 py-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Month') }}</flux:table.column>
                        <flux:table.column>{{ __('Currency') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($overview['paid_by_month'] as $row)
                            <flux:table.row wire:key="pm-{{ $row['period'] }}-{{ $row['currency'] }}">
                                <flux:table.cell>{{ $row['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['currency'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($row['minor'] / 100, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-zinc-500">{{ __('No active services with pricing.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
            <flux:card class="overflow-hidden p-0">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Paid by year') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs text-zinc-500">{{ __('Active services · recurring price per year') }}</flux:text>
                </div>
                <flux:table container:class="px-4 sm:px-6 py-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Year') }}</flux:table.column>
                        <flux:table.column>{{ __('Currency') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($overview['paid_by_year'] as $row)
                            <flux:table.row wire:key="py-{{ $row['year'] }}-{{ $row['currency'] }}">
                                <flux:table.cell variant="strong">{{ $row['year'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['currency'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($row['minor'] / 100, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-zinc-500">{{ __('No active services with pricing.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>

        <flux:card class="mt-4 p-6 space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Registered at') }}</flux:text>
                    <flux:text class="mt-1 font-medium text-zinc-800 dark:text-white">
                        {{ $client->registered_at?->format('Y-m-d') ?? '—' }}
                    </flux:text>
                </div>
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('VAT') }}</flux:text>
                    <flux:text class="mt-1 font-medium text-zinc-800 dark:text-white">
                        @if ($client->vat_exception)
                            <flux:badge size="sm" color="green">{{ __('exception') }}</flux:badge>
                        @else
                            {{ number_format($client->effectiveVatRatePercent(), 2) }}%
                        @endif
                    </flux:text>
                </div>
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('IRS') }}</flux:text>
                    <flux:text class="mt-1 font-medium text-zinc-800 dark:text-white">
                        @if ($client->irs_exception)
                            <flux:badge size="sm" color="green">{{ __('exception') }}</flux:badge>
                        @else
                            {{ number_format($client->effectiveIrsRatePercent(), 2) }}%
                        @endif
                    </flux:text>
                </div>
            </div>

            <div class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            @if ($client->company_name)
                <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('Company') }}:</span> {{ $client->company_name }}</flux:text>
            @endif
            @if ($client->address_line)
                <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('Address') }}:</span> {{ $client->address_line }}, {{ $client->postal_code }} {{ $client->city }}, {{ $client->country }}</flux:text>
            @endif
            @if ($client->phone)
                <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('Phone') }}:</span> {{ $client->phone }}</flux:text>
            @endif
            @if ($client->vat_number)
                <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('VAT') }}:</span> {{ $client->vat_number }}</flux:text>
            @endif
            @if ($client->notes)
                <flux:text class="whitespace-pre-wrap"><span class="font-medium text-zinc-800 dark:text-white">{{ __('Notes') }}:</span> {{ $client->notes }}</flux:text>
            @endif
            <flux:text>
                <span class="font-medium text-zinc-800 dark:text-white">{{ __('WHMCS #') }}</span>
                {{ $client->whmcs_id ?? '—' }}
            </flux:text>
            </div>
        </flux:card>
    @endif

    @if ($tab === 'contacts')
        <flux:card class="mt-4 overflow-hidden p-0">
            <flux:table container:class="px-4 sm:px-6 py-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Primary') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($client->contacts as $contact)
                        <flux:table.row wire:key="contact-{{ $contact->id }}">
                            <flux:table.cell variant="strong">{{ $contact->name }}</flux:table.cell>
                            <flux:table.cell>{{ $contact->email }}</flux:table.cell>
                            <flux:table.cell>{{ $contact->role->value }}</flux:table.cell>
                            <flux:table.cell>{{ $contact->is_primary ? __('Yes') : __('No') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    @unless ($contact->is_primary)
                                        <flux:button size="xs" variant="ghost" wire:click="setPrimaryContact({{ $contact->id }})">{{ __('Primary') }}</flux:button>
                                    @endunless
                                    <flux:button size="xs" variant="ghost" wire:click="deleteContact({{ $contact->id }})" wire:confirm="{{ __('Remove this contact?') }}">{{ __('Remove') }}</flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card class="mt-4 p-6">
            <flux:heading size="sm" class="mb-4">{{ __('Add contact') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="contact_name" :label="__('Name')" />
                <flux:input wire:model="contact_email" :label="__('Email')" type="email" />
                <flux:input wire:model="contact_phone" :label="__('Phone')" />
                <flux:select wire:model="contact_role" :label="__('Role')">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:checkbox wire:model="contact_is_primary" class="mt-4" :label="__('Primary contact')" />
            <flux:button class="mt-4" variant="primary" wire:click="addContact">{{ __('Add contact') }}</flux:button>
        </flux:card>
    @endif

    @if ($tab === 'services')
        <flux:card class="mt-4 overflow-hidden p-0">
            <flux:table container:class="px-4 sm:px-6 py-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Domain') }}</flux:table.column>
                    <flux:table.column>{{ __('Product') }}</flux:table.column>
                    <flux:table.column>{{ __('Server') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Due at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Recurring') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($client->services as $service)
                        <flux:table.row wire:key="svc-{{ $service->id }}">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('billing.services.edit', $service)" icon="eye" wire:navigate>{{ $service->id }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $service->associated_domain }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($service->product)
                                    <flux:link :href="route('billing.products.show', $service->product)" wire:navigate>{{ $service->product->name }}</flux:link>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($service->server)
                                    <flux:link :href="route('billing.servers.show', $service->server)" wire:navigate>{{ $service->server->name }}</flux:link>
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
                            <flux:table.cell colspan="7">{{ __('No services yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'domains')
        <flux:card class="mt-4 overflow-hidden p-0">
            <flux:table container:class="px-4 sm:px-6 py-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Domain') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Due at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Recurring') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($client->domains as $domain)
                        <flux:table.row wire:key="dom-{{ $domain->id }}">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('billing.domains.edit', $domain)" wire:navigate>{{ $domain->id }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('billing.domains.edit', $domain)" wire:navigate>{{ $domain->fqdn }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="center">{{ $domain->due_at?->format('Y-m-d') }}</flux:table.cell>
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
                            <flux:table.cell colspan="5">{{ __('No domains yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'orders')
        <flux:card class="mt-4 overflow-hidden p-0">
            <flux:table container:class="px-4 sm:px-6 py-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Issued at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Due at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($client->orders as $order)
                        <flux:table.row wire:key="ord-{{ $order->id }}">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('billing.orders.edit', $order)" wire:navigate>{{ $order->id }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $order->issued_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $order->due_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($order->total_minor / 100, 2) }} {{ $order->currency }}</flux:table.cell>
                            <flux:table.cell align="center">
                                <flux:badge size="sm" :color="$order->status->badgeColor()" class="capitalize">
                                    {{ $order->status->value }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __('No orders yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'emails')
        <flux:card class="mt-4 overflow-hidden p-0">
            <flux:table container:class="px-4 sm:px-6 py-3">
                <flux:table.columns>
                    <flux:table.column align="center">{{ __('Actions') }}</flux:table.column>
                    <flux:table.column>{{ __('Subject') }}</flux:table.column>
                    <flux:table.column>{{ __('To') }}</flux:table.column>
                    <flux:table.column>{{ __('Sent at') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($client->emailLogs as $log)
                        <flux:table.row wire:key="email-log-{{ $log->id }}">
                            <flux:table.cell align="center">
                                <flux:button size="xs" variant="ghost" wire:click="viewEmailLog({{ $log->id }})">{{ __('View') }}</flux:button>
                            </flux:table.cell>
                            <flux:table.cell>{{ $log->sent_at->format('Y-m-d H:i') }}</flux:table.cell>
                            <flux:table.cell variant="strong">{{ $log->subject }}</flux:table.cell>
                            <flux:table.cell>{{ $log->to_address }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ __('No emails sent yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <flux:modal wire:model="showEmailLogModal" class="max-w-3xl" @close="closeEmailLog">
        @if ($viewingEmailLog)
            <div class="space-y-4">
                <flux:heading size="lg">{{ $viewingEmailLog->subject }}</flux:heading>
                <div class="grid gap-2 text-sm">
                    <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('To') }}:</span> {{ $viewingEmailLog->to_address }}</flux:text>
                    @if ($viewingEmailLog->cc)
                        <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('CC') }}:</span> {{ $viewingEmailLog->cc }}</flux:text>
                    @endif
                    @if ($viewingEmailLog->bcc)
                        <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('BCC') }}:</span> {{ $viewingEmailLog->bcc }}</flux:text>
                    @endif
                    <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('Sent at') }}:</span> {{ $viewingEmailLog->sent_at->format('Y-m-d H:i') }}</flux:text>
                    @if ($viewingEmailLog->email_template_slug)
                        <flux:text><span class="font-medium text-zinc-800 dark:text-white">{{ __('Template') }}:</span> {{ $viewingEmailLog->email_template_slug }}</flux:text>
                    @endif
                </div>
                @if ($viewingEmailLog->hasAttachments())
                    <div class="space-y-1">
                        <flux:text class="font-medium text-zinc-800 dark:text-white">{{ __('Attachments') }}</flux:text>
                        @foreach ($viewingEmailLog->attachmentFiles() as $index => $file)
                            <flux:link :href="route('billing.clients.email-log-attachment', [$viewingEmailLog, $index])" target="_blank">
                                {{ $file['name'] ?? __('Attachment') }}
                            </flux:link>
                        @endforeach
                    </div>
                @endif
                <flux:separator />
                <div class="prose prose-sm prose-p:my-0 dark:prose-invert max-w-none rounded-lg border border-zinc-200 px-2 py-1 dark:border-zinc-700 whitespace-pre-wrap text-[11px] leading-4">
                    {!! $viewingEmailLog->message !!}
                </div>
                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="closeEmailLog">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
