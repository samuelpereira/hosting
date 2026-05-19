<section class="mx-auto w-full max-w-3xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Company and billing details') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:input wire:model="company_name" :label="__('Company name')" />
        <flux:input wire:model="email" :label="__('Email')" type="email" required />
        <flux:input wire:model="address_line" :label="__('Address')" />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="postal_code" :label="__('Postal code')" />
            <flux:input wire:model="city" :label="__('City')" />
        </div>
        <flux:input wire:model="country" :label="__('Country (ISO-2)')" maxlength="2" />
        <flux:input wire:model="phone" :label="__('Phone')" />
        <flux:input wire:model="vat_number" :label="__('VAT number')" />
        <div class="space-y-2">
            <flux:checkbox wire:model="vat_exception" :label="__('VAT exception')" />
            <flux:text class="text-sm text-zinc-500">
                {{ __('Configured VAT rate: :rate%. When enabled, this client is charged 0% VAT.', ['rate' => number_format((float) $taxSettings->vat_rate_percent, 2)]) }}
            </flux:text>
        </div>
        <div class="space-y-2">
            <flux:checkbox wire:model="irs_exception" :label="__('IRS exception')" />
            <flux:text class="text-sm text-zinc-500">
                {{ __('Configured IRS rate: :rate%. When enabled, this client is charged 0% IRS.', ['rate' => number_format((float) $taxSettings->irs_rate_percent, 2)]) }}
            </flux:text>
            <flux:link :href="route('billing.tax-settings.edit')" wire:navigate class="text-sm">{{ __('Edit tax settings') }}</flux:link>
        </div>
        <flux:input wire:model="registered_at" type="date" :label="__('Registered at')" />
        <flux:input wire:model="currency" :label="__('Currency (ISO-3)')" maxlength="3" required />
        <flux:checkbox wire:model="is_active" :label="__('Active')" />
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button
                variant="ghost"
                :href="$client ? route('billing.clients.show', $client) : route('billing.clients.index')"
                wire:navigate
                type="button"
            >{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
