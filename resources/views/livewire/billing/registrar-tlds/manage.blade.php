<section class="mx-auto w-full max-w-4xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('TLD details and price rows') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:select wire:model="registrar_id" :label="__('Registrar')" required>
            <option value="">{{ __('Select…') }}</option>
            @foreach ($registrars as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="tld" :label="__('TLD (without dot)')" placeholder="com" required />
        <flux:input
            wire:model="whois_link_template"
            :label="__('WHOIS link template')"
            placeholder="https://rdap.registrar.cloudflare.com/{fqdn}/whois"
        />
        <flux:text class="text-sm text-zinc-500">
            {{ __('Optional. Placeholders: {fqdn}, {site}, {tld}, {tld_dot}.') }}
        </flux:text>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('Prices') }}</flux:heading>
                <flux:button type="button" size="sm" variant="outline" wire:click="addPriceRow" icon="plus">{{ __('Add row') }}</flux:button>
            </div>
            @foreach ($priceRows as $index => $row)
                <flux:card class="p-4 space-y-3" wire:key="price-{{ $index }}-{{ $row['id'] ?? 'new' }}">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <flux:select wire:model="priceRows.{{ $index }}.operation" :label="__('Operation')">
                            @foreach ($operations as $op)
                                <option value="{{ $op->value }}">{{ \Illuminate\Support\Str::headline($op->value) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="priceRows.{{ $index }}.term_years" type="number" min="1" max="10" :label="__('Term (years)')" />
                        <flux:input wire:model="priceRows.{{ $index }}.currency" maxlength="3" :label="__('Currency')" />
                        <flux:input wire:model="priceRows.{{ $index }}.amount_input" :label="__('Sell price')" />
                        <flux:input wire:model="priceRows.{{ $index }}.domain_cost_input" :label="__('Domain cost')" placeholder="{{ __('Optional') }}" />
                        <div class="flex items-end justify-between gap-2 sm:col-span-2 lg:col-span-3">
                            <flux:checkbox wire:model="priceRows.{{ $index }}.is_active" :label="__('Active')" />
                            <flux:button type="button" size="sm" variant="ghost" wire:click="removePriceRow({{ $index }})">{{ __('Remove') }}</flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('billing.registrar-tlds.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
