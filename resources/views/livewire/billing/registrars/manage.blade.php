<section class="mx-auto w-full max-w-2xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:input wire:model="code" :label="__('Code')" required />
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
        <flux:checkbox wire:model="is_active" :label="__('Active')" />
        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('billing.registrars.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
