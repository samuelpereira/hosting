<section class="mx-auto w-full max-w-2xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ __('Company details') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Your business information for invoices and client emails') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:card class="space-y-4 p-6">
            <flux:input wire:model="name" :label="__('Company name')" />
            <flux:input wire:model="address_line" :label="__('Address')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="postal_code" :label="__('Zip code')" />
                <flux:input wire:model="city" :label="__('City')" />
            </div>
            <flux:input wire:model="country" :label="__('Country (ISO-2)')" maxlength="2" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
            </div>
            <flux:textarea wire:model="bank_account" :label="__('Bank account')" rows="3" />
        </flux:card>

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</section>
