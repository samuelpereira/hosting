<section class="mx-auto w-full max-w-3xl px-4 py-8 lg:px-8">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Host, cost, and capacity hints (RAM / disk in specs)') }}</flux:subheading>

    @if ($server)
        <flux:card class="mt-6 space-y-3 p-4">
            <flux:heading size="sm">{{ __('Active service allocation') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('RAM and disk summed from services in active status, as a share of capacity from specs.') }}</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('RAM') }}</flux:text>
                    <flux:heading size="md" class="mt-1">
                        {{ \App\Models\Server::formatRamMb($server->allocatedRamMbActiveServices()) }}
                        @if ($server->capacityRamMb() !== null)
                            <span class="text-base font-normal text-zinc-500">/ {{ \App\Models\Server::formatRamMb($server->capacityRamMb()) }}</span>
                            @if ($server->ramUsagePercentActive() !== null)
                                <span class="text-base font-normal text-zinc-500">({{ $server->ramUsagePercentActive() }}%)</span>
                            @endif
                        @endif
                    </flux:heading>
                </div>
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Disk') }}</flux:text>
                    <flux:heading size="md" class="mt-1">
                        {{ \App\Models\Server::formatDiskGb($server->allocatedDiskGbActiveServices()) }}
                        @if ($server->capacityDiskGb() !== null)
                            <span class="text-base font-normal text-zinc-500">/ {{ \App\Models\Server::formatDiskGb($server->capacityDiskGb()) }}</span>
                            @if ($server->diskUsagePercentActive() !== null)
                                <span class="text-base font-normal text-zinc-500">({{ $server->diskUsagePercentActive() }}%)</span>
                            @endif
                        @endif
                    </flux:heading>
                </div>
            </div>
        </flux:card>
    @endif

    <form wire:submit="save" class="mt-8 space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:input wire:model="hostname" :label="__('Hostname')" required />
        <flux:input wire:model="panel_url" type="url" :label="__('Panel URL')" placeholder="https://panel.example.com:10000" />
        <flux:text class="text-sm text-zinc-500">{{ __('Control panel link included in the service welcome email.') }}</flux:text>
        <flux:select wire:model="status" :label="__('Status')">
            @foreach ($statuses as $st)
                <option value="{{ $st->value }}">{{ $st->name }}</option>
            @endforeach
        </flux:select>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="cost_amount_input" :label="__('Monthly cost (major)')" />
            <flux:input wire:model="cost_currency" :label="__('Cost currency')" maxlength="3" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="ram_gb" type="number" :label="__('RAM (GiB) — stored in specs')" />
            <flux:input wire:model="disk_gb" type="number" :label="__('Disk (GB) — stored in specs')" />
        </div>
        <flux:input wire:model="cpu" :label="__('CPU label')" />
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

        <div class="flex gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="$server ? route('billing.servers.show', $server) : route('billing.servers.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
