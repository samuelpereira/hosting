<section class="mx-auto w-full max-w-2xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ __('Tax settings') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Default VAT and IRS rates applied to clients without an exception') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Rates') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                {{ __('When a client has VAT exception or IRS exception enabled, that rate is treated as 0% for them.') }}
            </flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="vat_rate_input" :label="__('VAT rate (%)')" inputmode="decimal" required />
                <flux:input wire:model="irs_rate_input" :label="__('IRS rate (%)')" inputmode="decimal" required />
            </div>
        </flux:card>

        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Domains') }}</flux:heading>
            <flux:checkbox wire:model="domains_irs_exception" :label="__('IRS exception for domain lines')" />
            <flux:text class="text-sm text-zinc-500">
                {{ __('When enabled, domain lines on orders (including renewals) do not have IRS retention. VAT still applies per client settings.') }}
            </flux:text>
        </flux:card>

        <flux:card class="space-y-2 p-6">
            <flux:heading size="sm">{{ __('How it works') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                {{ __('Configure each client under Clients → Edit. VAT exception and IRS exception flags control whether these rates apply to that client’s orders.') }}
            </flux:text>
        </flux:card>

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</section>
