<section class="mx-auto w-full max-w-3xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="mt-1">
        {{ __('Use') }}
        <code class="font-mono text-sm">{{ $placeholderSyntax }}</code>
        {{ __('placeholders in subject and body.') }}
    </flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:input wire:model="slug" :label="__('Slug')" required />
        <flux:text class="text-sm text-zinc-500">{{ __('Unique identifier, e.g. order_created') }}</flux:text>

        <flux:input wire:model="subject" :label="__('Subject')" required />

        <flux:textarea wire:model="body" :label="__('Body (HTML)')" rows="14" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

        <flux:input
            wire:model="variables_input"
            :label="__('Variables')"
            placeholder="client_name, order_number, company_name"
        />
        <flux:text class="text-sm text-zinc-500">{{ __('Comma-separated list of placeholders available in this template.') }}</flux:text>

        <flux:checkbox wire:model="is_active" :label="__('Active')" />

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('billing.email-templates.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
