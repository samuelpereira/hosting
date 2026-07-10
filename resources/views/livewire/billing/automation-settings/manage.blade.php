<section class="mx-auto w-full max-w-2xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ __('Automation') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Daily renewal orders and unpaid order reminders') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Schedule') }}</flux:heading>
            <flux:checkbox wire:model="is_enabled" :label="__('Automation enabled')" />
            <flux:input wire:model="run_at_time" type="time" :label="__('Run daily at')" />
            <flux:text class="text-sm text-zinc-500">
                {{ __('Add to server crontab: * * * * * php artisan schedule:run') }}
            </flux:text>
            <flux:text class="text-sm text-zinc-500">
                {{ __('Laravel runs billing:run-automation hourly and executes at this time.') }}
            </flux:text>
        </flux:card>

        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Renewal orders') }}</flux:heading>
            <flux:input wire:model="service_order_days_before" type="number" min="0" max="365" :label="__('Services (with synced domain): days before due_at')" />
            <flux:text class="text-sm text-zinc-500">
                {{ __('Creates one order with product and domain lines when a service is linked to a domain.') }}
            </flux:text>
            <flux:input wire:model="domain_order_days_before" type="number" min="0" max="365" :label="__('Standalone domains: days before due_at')" />
            <flux:text class="text-sm text-zinc-500">
                {{ __('Domains without a billable active service (or with only a free linked service) get their own order.') }}
            </flux:text>
        </flux:card>

        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Unpaid order reminders') }}</flux:heading>
            <flux:input wire:model="reminder_days_before_first" type="number" min="0" max="365" :label="__('Days before order due_at')" />
            <flux:input wire:model="reminder_days_before_due" type="number" min="0" max="365" :label="__('On order due_at (days before)')" />
            <flux:input wire:model="reminder_days_after_due" type="number" min="0" max="365" :label="__('Days after order due_at')" />
            <flux:checkbox wire:model="send_order_created_email" :label="__('Send order_created email when auto-generating orders')" />
            <flux:checkbox wire:model="send_reminder_emails" :label="__('Send order_payment_reminder emails')" />
        </flux:card>

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</section>
