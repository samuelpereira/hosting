<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Email templates') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Transactional messages sent to clients') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('billing.email-templates.create')" wire:navigate icon="plus">{{ __('Add template') }}</flux:button>
    </div>

    <flux:card class="mt-6 p-4">
        <flux:input wire:model.live.debounce.300ms="q" :label="__('Search')" placeholder="{{ __('Name, slug, subject') }}" />
    </flux:card>

    <flux:card class="mt-4 overflow-hidden p-0">
        <flux:table container:class="px-4 sm:px-6 py-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Slug') }}</flux:table.column>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Active') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($templates as $template)
                    <flux:table.row wire:key="email-tpl-{{ $template->id }}">
                        <flux:table.cell variant="strong">{{ $template->name }}</flux:table.cell>
                        <flux:table.cell><code class="text-sm">{{ $template->slug }}</code></flux:table.cell>
                        <flux:table.cell>{{ \Illuminate\Support\Str::limit($template->subject, 48) }}</flux:table.cell>
                        <flux:table.cell>{{ $template->is_active ? __('Yes') : __('No') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:link :href="route('billing.email-templates.edit', $template)" wire:navigate>{{ __('Edit') }}</flux:link>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('No email templates found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @if ($templates->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $templates->links() }}</div>
        @endif
    </flux:card>
</section>
