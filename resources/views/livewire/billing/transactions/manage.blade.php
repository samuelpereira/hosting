<section class="mx-auto w-full max-w-2xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <form wire:submit="save" class="mt-8 space-y-6">
        @if ($transaction)
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Order') }}</flux:heading>
                <flux:card class="flex flex-col gap-3 p-4">
                    <div>
                        <flux:link :href="route('billing.orders.edit', $order)" wire:navigate class="mt-0.5 block font-medium">
                            {{ $order?->number }}
                        </flux:link>
                    </div>
                </flux:card>
            </div>
        @else
            <flux:select wire:model.live="order_id" :label="__('Order')" required>
                <option value="">{{ __('Select…') }}</option>
                @foreach ($orders as $ord)
                    <option value="{{ $ord->id }}">{{ $ord->number }} — {{ $ord->client?->name }}</option>
                @endforeach
            </flux:select>
        @endif
        <flux:input wire:model="reference" :label="__('Reference')" />
        <flux:select wire:model="method" :label="__('Method')">
            <option value="">{{ __('None') }}</option>
            @foreach ($methods as $paymentMethod)
                <option value="{{ $paymentMethod->value }}">{{ $paymentMethod->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model="status" :label="__('Status')">
            @foreach ($statuses as $st)
                <option value="{{ $st->value }}">{{ $st->name }}</option>
            @endforeach
        </flux:select>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" />
            <flux:input wire:model="amount_input" :label="__('Amount (major)')" />
        </div>
        <flux:input wire:model="paid_at" type="date" :label="__('Paid at')" />
        <div class="space-y-2">
            <flux:text class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Document') }}</flux:text>
            <input
                type="file"
                wire:model="document"
                accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx"
                class="block w-full cursor-pointer rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-200 dark:hover:file:bg-zinc-600"
            />
            <flux:text class="text-xs text-zinc-500">{{ __('Receipt, invoice, or proof of payment. PDF, image, or Word. Max 10 MB.') }}</flux:text>
            <div wire:loading wire:target="document" class="text-xs text-zinc-500">{{ __('Uploading…') }}</div>
            @error('document')
                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
            @if ($transaction?->hasDocument() && ! $remove_document && ! $document)
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:link
                        :href="route('billing.transactions.document', $transaction)"
                        class="text-sm font-medium"
                        icon="arrow-down-tray"
                    >{{ $transaction->document_name }}</flux:link>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="removeDocument">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            @endif
        </div>
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('billing.transactions.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
