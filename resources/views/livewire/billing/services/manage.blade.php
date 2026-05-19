<section class="mx-auto w-full max-w-3xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Client, product, pricing, optional server, and optional domain') }}</flux:subheading>

    @if ($service?->id)
        <div class="mt-4 flex flex-wrap gap-2">
            <flux:button
                size="sm"
                variant="outline"
                :href="route('billing.orders.create', ['client_id' => $service->client_id, 'service_id' => $service->id])"
                wire:navigate
                icon="document-text"
            >{{ __('Generate order') }}</flux:button>
        </div>
    @endif

    <form wire:submit="save" class="mt-8 space-y-6">
        @if ($service)
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="sm">{{ __('Client') }}</flux:heading>
                    @if (! $show_move_service)
                        <flux:button type="button" size="sm" variant="outline" wire:click="$set('show_move_service', true)">
                            {{ __('Move service') }}
                        </flux:button>
                    @endif
                </div>
                <flux:card class="space-y-1 p-4">
                    <flux:link :href="route('billing.clients.show', $service->client_id)" wire:navigate class="font-medium">
                        {{ $service->client?->name }}
                    </flux:link>
                    @if ($service->client?->email)
                        <flux:text class="text-sm text-zinc-500">{{ $service->client->email }}</flux:text>
                    @endif
                    @if ($service->client?->company_name)
                        <flux:text class="text-sm text-zinc-500">{{ $service->client->company_name }}</flux:text>
                    @endif
                </flux:card>

                @if ($show_move_service)
                    <flux:card class="space-y-4 p-4">
                        <flux:heading size="sm">{{ __('Move service to another client') }}</flux:heading>
                        <flux:input
                            wire:model.live.debounce.300ms="move_client_search"
                            :label="__('Search client')"
                            placeholder="{{ __('Name, email, or company') }}"
                        />
                        @if (strlen(trim($move_client_search)) >= 2)
                            <div class="space-y-1">
                                @forelse ($moveClientResults as $candidate)
                                    <button
                                        type="button"
                                        wire:click="selectMoveTargetClient({{ $candidate->id }})"
                                        class="flex w-full flex-col rounded-md px-3 py-2 text-start text-sm transition {{ $move_target_client_id === $candidate->id ? 'bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-950/50 dark:ring-indigo-400' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                                    >
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $candidate->name }}</span>
                                        @if ($candidate->email)
                                            <span class="text-zinc-500">{{ $candidate->email }}</span>
                                        @endif
                                    </button>
                                @empty
                                    <flux:text class="text-sm text-zinc-500">{{ __('No clients found.') }}</flux:text>
                                @endforelse
                            </div>
                        @elseif (trim($move_client_search) !== '')
                            <flux:text class="text-sm text-zinc-500">{{ __('Type at least 2 characters to search.') }}</flux:text>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <flux:button
                                type="button"
                                variant="primary"
                                wire:click="moveService"
                                wire:confirm="{{ __('Move this service to the selected client?') }}"
                                :disabled="! $move_target_client_id"
                            >{{ __('Move service') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="$set('show_move_service', false)">{{ __('Cancel') }}</flux:button>
                        </div>
                    </flux:card>
                @endif
            </div>
        @else
            <flux:select wire:model.live="client_id" :label="__('Client')" required>
                <option value="">{{ __('Select…') }}</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model="server_id" :label="__('Server')">
            <option value="">{{ __('None') }}</option>
            @foreach ($servers as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="product_id" :label="__('Product')" required>
            <option value="">{{ __('Select…') }}</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </flux:select>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="username" :label="__('Username')" autocomplete="off" />
            <flux:input wire:model="service_password" :label="__('Password')" type="password" viewable autocomplete="new-password" />
        </div>
        <flux:text class="text-sm text-zinc-500">{{ __('Included in the service welcome email (username, password placeholders).') }}</flux:text>

        <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="first_payment_amount_input" :label="__('First payment amount')" />
            <flux:input wire:model="recurring_amount_input" :label="__('Recurring amount')" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="interval_unit" :label="__('Billing interval')">
                @foreach ($intervalUnits as $iu)
                    <option value="{{ $iu->value }}">{{ \Illuminate\Support\Str::headline($iu->value) }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="interval_count" type="number" min="1" max="120" :label="__('Every')" />
        </div>
        <flux:text class="text-sm text-zinc-500">{{ __('Recurring amount is charged per this interval (e.g. every 3 months).') }}</flux:text>

        <flux:input wire:model="associated_domain" :label="__('Associated domain (FQDN)')" placeholder="client.example.com" />
        <flux:text class="text-sm text-zinc-500">{{ __('Stored only on this service (per client), so each customer can use the same catalog product with their own hostname.') }}</flux:text>

        @if (! $service?->domain_id)
            <flux:checkbox wire:model.live="create_domain" :label="__('Create domain record now (same client)')" />
        @else
            <flux:callout variant="subtle">{{ __('This service is already linked to a domain record.') }}</flux:callout>
        @endif

        @if ($create_domain && ! $service?->domain_id)
            <flux:card class="space-y-4 p-4">
                <flux:heading size="sm">{{ __('New domain') }}</flux:heading>
                <flux:select wire:model.live="domain_registrar_id" :label="__('Registrar')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($registrars as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="domain_registrar_tld_id" :label="__('TLD')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($tldOptions as $t)
                        <option value="{{ $t->id }}">.{{ $t->tld }}</option>
                    @endforeach
                </flux:select>
                @if ($domain_registrar_tld_id)
                    <flux:select wire:model.live="domain_registrar_price_operation" :label="__('Registrar operation')">
                        @foreach ($registrarTldPriceOperations as $op)
                            <option value="{{ $op->value }}">{{ $op->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="domain_registrar_tld_price_id" :label="__('TLD price')" required>
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($domainTldPrices as $pp)
                            <option value="{{ $pp->id }}">
                                {{ $pp->term_years }} {{ __('yr') }}
                                · {{ $pp->currency }} {{ number_format($pp->amount_minor / 100, 2) }}
                                @if ($pp->domain_cost_minor !== null)
                                    ({{ __('cost') }} {{ number_format($pp->domain_cost_minor / 100, 2) }})
                                @endif
                            </option>
                        @endforeach
                    </flux:select>
                    @if ($domainTldPrices->isEmpty())
                        <flux:callout variant="subtle">{{ __('No active prices for this TLD and operation. Add them on the registrar TLD edit page.') }}</flux:callout>
                    @endif
                @endif
                <flux:input wire:model="domain_fqdn" :label="__('FQDN')" required />
                <flux:select wire:model="domain_status" :label="__('Status')">
                    @foreach ($domainStatuses as $st)
                        <option value="{{ $st->value }}">{{ $st->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="domain_currency" maxlength="3" :label="__('Currency')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="domain_first_payment_amount_input" :label="__('First payment amount')" />
                    <flux:input wire:model="domain_recurring_amount_input" :label="__('Recurring amount')" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="domain_interval_unit" :label="__('Domain billing interval')">
                        @foreach ($intervalUnits as $iu)
                            <option value="{{ $iu->value }}">{{ \Illuminate\Support\Str::headline($iu->value) }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="domain_interval_count" type="number" min="1" max="120" :label="__('Every')" />
                </div>
                <flux:text class="text-sm text-zinc-500">{{ __('Billing interval from the selected TLD price. Registrar cost is on the TLD price (shown in the dropdown).') }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="domain_registered_at" type="date" :label="__('Registered at')" required />
                    <flux:input wire:model="domain_expires_at" type="date" :label="__('Expires at')" required />
                    <flux:input wire:model="domain_due_at" type="date" :label="__('Due at')" required />
                </div>
                <flux:checkbox wire:model="domain_auto_renew" :label="__('Auto renew')" />
                @error('domain_registrar_tld_id')
                    <flux:callout variant="danger">{{ $message }}</flux:callout>
                @enderror
                @error('domain_registrar_tld_price_id')
                    <flux:callout variant="danger">{{ $message }}</flux:callout>
                @enderror
            </flux:card>
        @endif

        <flux:select wire:model="status" :label="__('Status')">
            @foreach ($statuses as $st)
                <option value="{{ $st->value }}">{{ $st->name }}</option>
            @endforeach
        </flux:select>
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="activated_at" type="date" :label="__('Activated')" />
            <flux:input wire:model="due_at" type="date" :label="__('Due at')" />
            <flux:input wire:model="cancelled_at" type="date" :label="__('Cancelled')" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="allocated_ram_mb" type="number" :label="__('Allocated RAM (MiB)')" />
            <flux:input wire:model="allocated_disk_gb" type="number" :label="__('Allocated disk (GB)')" />
        </div>
        <flux:text class="text-sm text-zinc-500">{{ __('Choosing a product fills these from the product defaults when set; you can change them anytime.') }}</flux:text>
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
        @error('allocated_ram_mb')
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @enderror
        @error('allocated_disk_gb')
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @enderror

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('billing.services.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>

    @if ($service?->id)
        <flux:card class="mt-10 space-y-4 p-6">
            <div>
                <flux:heading size="sm">{{ __('Send email') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Send the service welcome email with access details (username, password, panel URL).') }}
                </flux:text>
            </div>

            @if ($recipientEmail)
                <flux:text class="text-sm">
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('To') }}:</span>
                    {{ $recipientEmail }}
                </flux:text>
            @else
                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('This service’s client has no email address.') }}
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
                <flux:text class="text-sm text-zinc-500">{{ __('No active service email templates. Add templates in Email templates.') }}</flux:text>
            @endif
        </flux:card>

        <flux:card class="mt-6 overflow-hidden p-0">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700 sm:px-6">
                <flux:heading size="sm">{{ __('Orders for this service') }}</flux:heading>
                <flux:text class="mt-0.5 text-sm text-zinc-500">{{ __('Line items of type “service” that point at this record.') }}</flux:text>
            </div>
            <flux:table container:class="px-4 sm:px-6 py-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('Number') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Issued') }}</flux:table.column>
                    <flux:table.column>{{ __('Paid at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($service->orders as $ord)
                        <flux:table.row wire:key="svc-inv-{{ $ord->id }}">
                            <flux:table.cell variant="strong">{{ $ord->number }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ord->status->badgeColor()" class="capitalize">
                                    {{ $ord->status->value }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $ord->issued_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $ord->paid_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $ord->currency }} {{ number_format($ord->total_minor / 100, 2) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:link :href="route('billing.orders.edit', $ord)" wire:navigate>{{ __('Edit') }}</flux:link>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __('No orders linked yet. Use “Generate order” above.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</section>
