<section class="mx-auto w-full max-w-2xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <form wire:submit="save" class="mt-8 space-y-6">
        @if ($domain)
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="sm">{{ __('Client') }}</flux:heading>
                    @if (! $show_move_domain)
                        <flux:button type="button" size="sm" variant="outline" wire:click="$set('show_move_domain', true)">
                            {{ __('Move domain') }}
                        </flux:button>
                    @endif
                </div>
                <flux:card class="space-y-1 p-4">
                    <flux:link :href="route('billing.clients.show', $domain->client_id)" wire:navigate class="font-medium">
                        {{ $domain->client?->name }}
                    </flux:link>
                    @if ($domain->client?->email)
                        <flux:text class="text-sm text-zinc-500">{{ $domain->client->email }}</flux:text>
                    @endif
                    @if ($domain->client?->company_name)
                        <flux:text class="text-sm text-zinc-500">{{ $domain->client->company_name }}</flux:text>
                    @endif
                </flux:card>

                @if ($show_move_domain)
                    <flux:card class="space-y-4 p-4">
                        <flux:heading size="sm">{{ __('Move domain to another client') }}</flux:heading>
                        <flux:input
                            wire:model.live.debounce.300ms="move_client_search"
                            :label="__('Search client')"
                            placeholder="{{ __('Name, email, or company') }}"
                        />
                        @error('move_target_client_id')
                            <flux:callout variant="danger">{{ $message }}</flux:callout>
                        @enderror
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
                                wire:click="moveDomain"
                                wire:confirm="{{ __('Move this domain to the selected client?') }}"
                                :disabled="! $move_target_client_id"
                            >{{ __('Move domain') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="$set('show_move_domain', false)">{{ __('Cancel') }}</flux:button>
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

        <flux:select wire:model.live="registrar_id" :label="__('Registrar')" required>
            <option value="">{{ __('Select…') }}</option>
            @foreach ($registrars as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="registrar_tld_id" :label="__('TLD (optional)')">
            <option value="">{{ __('None') }}</option>
            @foreach ($tldOptions as $t)
                <option value="{{ $t->id }}">.{{ $t->tld }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="fqdn" :label="__('FQDN')" required />
        <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="first_payment_amount_input" :label="__('First payment amount')" />
            <flux:input wire:model="recurring_amount_input" :label="__('Recurring amount')" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="interval_unit" :label="__('Billing interval')">
                @foreach ($intervalUnits as $iu)
                    <option value="{{ $iu->value }}">{{ \Illuminate\Support\Str::headline($iu->value) }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="interval_count" type="number" min="1" max="120" :label="__('Every')" />
        </div>
        <flux:text class="text-sm text-zinc-500">{{ __('Billing interval defaults from the selected TLD. Registrar cost is configured on the TLD price.') }}</flux:text>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="registered_at" type="date" :label="__('Registered at')" required />
            <flux:input wire:model="expires_at" type="date" :label="__('Expires at')" required />
        </div>
        <flux:input wire:model="due_at" type="date" :label="__('Due at')" required />
        <flux:select wire:model="status" :label="__('Status')">
            @foreach ($statuses as $st)
                <option value="{{ $st->value }}">{{ $st->name }}</option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model="auto_renew" :label="__('Auto renew')" />
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
        @error('registrar_tld_id')
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @enderror
        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('billing.domains.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
