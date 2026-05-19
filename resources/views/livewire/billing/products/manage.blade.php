<section class="mx-auto w-full max-w-4xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Product details and price rows') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:select wire:model="type" :label="__('Type')">
            @foreach ($productTypes as $pt)
                <option value="{{ $pt->value }}">{{ $pt->name }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:input wire:model="slug" :label="__('Slug')" required />
        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="default_allocated_ram_gb" type="number" min="0" :label="__('Default RAM allocation (GB)')" />
            <flux:input wire:model="default_allocated_disk_gb" type="number" min="0" :label="__('Default disk allocation (GB)')" />
        </div>
        <flux:text class="text-sm text-zinc-500">{{ __('Used to pre-fill new services for this product; you can still override on each service.') }}</flux:text>
        <flux:checkbox wire:model="is_active" :label="__('Active')" />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('Prices') }}</flux:heading>
                <flux:button type="button" size="sm" variant="outline" wire:click="addPriceRow" icon="plus">{{ __('Add row') }}</flux:button>
            </div>
            @foreach ($priceRows as $index => $row)
                <flux:card class="p-4 space-y-3" wire:key="price-{{ $index }}-{{ $row['id'] ?? 'new' }}">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <flux:select wire:model="priceRows.{{ $index }}.billing_kind" :label="__('Billing')">
                            @foreach ($billingKinds as $bk)
                                <option value="{{ $bk->value }}">{{ $bk->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="priceRows.{{ $index }}.interval_unit" :label="__('Interval')">
                            @foreach ($intervalUnits as $iu)
                                <option value="{{ $iu->value }}">{{ $iu->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="priceRows.{{ $index }}.interval_count" type="number" min="1" :label="__('Every')" />
                        <flux:input wire:model="priceRows.{{ $index }}.currency" maxlength="3" :label="__('Currency')" />
                        <flux:input wire:model="priceRows.{{ $index }}.amount_input" :label="__('Amount (major)')" />
                        <div class="flex items-end justify-between gap-2">
                            <flux:checkbox wire:model="priceRows.{{ $index }}.is_active" :label="__('Active')" />
                            <flux:button type="button" size="sm" variant="ghost" wire:click="removePriceRow({{ $index }})">{{ __('Remove') }}</flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="$product ? route('billing.products.show', $product) : route('billing.products.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
