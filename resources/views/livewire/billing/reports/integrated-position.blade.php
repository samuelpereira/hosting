@php
    use App\Services\Billing\IntegratedPositionReport as Report;
@endphp

<section class="mx-auto w-full max-w-6xl px-4 py-8 lg:px-8">
    <div>
        <flux:heading size="xl">{{ __('Integrated position') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Monthly and yearly totals from active products and domains, minus allocated costs.') }}
        </flux:subheading>
    </div>

    @php
        $summaryCurrencies = array_values(array_unique(array_merge(
            array_keys($report['summary_by_currency'] ?? []),
            array_keys($report['summary_yearly_by_currency'] ?? []),
        )));
        sort($summaryCurrencies);
    @endphp

    @if ($summaryCurrencies === [])
        <flux:card class="mt-6 p-6">
            <flux:text>{{ __('No active services or domains with billing data.') }}</flux:text>
        </flux:card>
    @else
        <div class="mt-6 space-y-4">
            @foreach ($summaryCurrencies as $currency)
                <div class="grid gap-4 sm:grid-cols-2">
                    @if (isset($report['summary_by_currency'][$currency]))
                        @php $monthlyTotals = $report['summary_by_currency'][$currency]; @endphp
                        <flux:card class="p-5">
                            <flux:subheading>{{ __('Totals / mo') }} ({{ $currency }})</flux:subheading>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Revenue') }}</dt>
                                    <dd class="font-medium text-emerald-600 dark:text-emerald-400">{{ Report::formatMinor($monthlyTotals['revenue_minor'], $currency) }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Expenses') }}</dt>
                                    <dd class="font-medium text-red-600 dark:text-red-400">{{ Report::formatMinor($monthlyTotals['expense_minor'], $currency) }}</dd>
                                </div>
                                <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                    <dt class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Profit') }}</dt>
                                    <dd @class([
                                        'font-semibold',
                                        'text-emerald-600 dark:text-emerald-400' => $monthlyTotals['profit_minor'] >= 0,
                                        'text-red-600 dark:text-red-400' => $monthlyTotals['profit_minor'] < 0,
                                    ])>{{ Report::formatMinor($monthlyTotals['profit_minor'], $currency) }}</dd>
                                </div>
                            </dl>
                        </flux:card>
                    @endif
                    @if (isset($report['summary_yearly_by_currency'][$currency]))
                        @php $yearlyTotals = $report['summary_yearly_by_currency'][$currency]; @endphp
                        <flux:card class="p-5">
                            <flux:subheading>{{ __('Totals / yr') }} ({{ $currency }})</flux:subheading>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Revenue') }}</dt>
                                    <dd class="font-medium text-emerald-600 dark:text-emerald-400">{{ Report::formatMinor($yearlyTotals['revenue_minor'], $currency) }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Expenses') }}</dt>
                                    <dd class="font-medium text-red-600 dark:text-red-400">{{ Report::formatMinor($yearlyTotals['expense_minor'], $currency) }}</dd>
                                </div>
                                <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                    <dt class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Profit') }}</dt>
                                    <dd @class([
                                        'font-semibold',
                                        'text-emerald-600 dark:text-emerald-400' => $yearlyTotals['profit_minor'] >= 0,
                                        'text-red-600 dark:text-red-400' => $yearlyTotals['profit_minor'] < 0,
                                    ])>{{ Report::formatMinor($yearlyTotals['profit_minor'], $currency) }}</dd>
                                </div>
                            </dl>
                        </flux:card>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <flux:heading size="lg" class="mt-10">{{ __('Products') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Active services grouped by product — revenue and allocated server cost') }}</flux:subheading>

    <flux:card class="mt-4 overflow-hidden p-0">
        <flux:table container:class="px-4 sm:px-6 py-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Revenue / mo') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Expense / mo') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Profit / mo') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($report['products'] as $row)
                    <flux:table.row :key="'product-'.($row['id'] ?? 'none').'-'.$row['currency']">
                        <flux:table.cell>
                            <a href="{{ $row['edit_url'] }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $row['label'] }}</a>
                            @if ($row['detail'] !== '')
                                <flux:text class="mt-0.5 text-xs">{{ $row['detail'] }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ Report::formatMinor($row['revenue_minor'], $row['currency']) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ Report::formatMinor($row['expense_minor'], $row['currency']) }}</flux:table.cell>
                        <flux:table.cell align="end" @class([
                            'text-emerald-600 dark:text-emerald-400' => $row['profit_minor'] >= 0,
                            'text-red-600 dark:text-red-400' => $row['profit_minor'] < 0,
                        ])>{{ Report::formatMinor($row['profit_minor'], $row['currency']) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No active services.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @foreach ($report['products_totals_by_currency'] as $currency => $totals)
            <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 border-t border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 sm:px-6">
                <span>{{ __('Subtotal') }} ({{ $currency }}):</span>
                <span class="text-emerald-600 dark:text-emerald-400">{{ __('Revenue') }} {{ Report::formatMinor($totals['revenue_minor'], $currency) }}</span>
                <span class="text-red-600 dark:text-red-400">{{ __('Expenses') }} {{ Report::formatMinor($totals['expense_minor'], $currency) }}</span>
                <span class="font-medium">{{ __('Profit') }} {{ Report::formatMinor($totals['profit_minor'], $currency) }}</span>
            </div>
        @endforeach
    </flux:card>

    <flux:heading size="lg" class="mt-10">{{ __('Domains') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Active domains — yearly renewal revenue and registrar cost; monthly row feeds overall totals') }}</flux:subheading>

    <flux:card class="mt-4 overflow-hidden p-0">
        <flux:table container:class="px-4 sm:px-6 py-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Revenue / yr') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Expense / yr') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Profit / yr') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($report['domains'] as $row)
                    <flux:table.row :key="'domain-'.$row['id']">
                        <flux:table.cell>
                            <a href="{{ $row['edit_url'] }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $row['label'] }}</a>
                            @if ($row['detail'] !== '')
                                <flux:text class="mt-0.5 text-xs">{{ $row['detail'] }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ Report::formatMinor($row['revenue_yearly_minor'], $row['currency']) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ Report::formatMinor($row['expense_yearly_minor'], $row['currency']) }}</flux:table.cell>
                        <flux:table.cell align="end" @class([
                            'text-emerald-600 dark:text-emerald-400' => $row['profit_yearly_minor'] >= 0,
                            'text-red-600 dark:text-red-400' => $row['profit_yearly_minor'] < 0,
                        ])>{{ Report::formatMinor($row['profit_yearly_minor'], $row['currency']) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No active domains.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        @foreach ($report['domains_totals_by_currency'] as $currency => $totals)
            <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 border-t border-zinc-200 bg-zinc-100 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-800/50 sm:px-6">
                <span class="font-medium">{{ __('Monthly') }} ({{ $currency }}):</span>
                <span class="text-emerald-600 dark:text-emerald-400">{{ __('Revenue') }} {{ Report::formatMinor($totals['revenue_minor'], $currency) }}</span>
                <span class="text-red-600 dark:text-red-400">{{ __('Expenses') }} {{ Report::formatMinor($totals['expense_minor'], $currency) }}</span>
                <span class="font-medium">{{ __('Profit') }} {{ Report::formatMinor($totals['profit_minor'], $currency) }}</span>
            </div>
        @endforeach
        @foreach ($report['domains_yearly_totals_by_currency'] as $currency => $totals)
            <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 border-t border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 sm:px-6">
                <span class="font-medium">{{ __('Subtotal / yr') }} ({{ $currency }}):</span>
                <span class="text-emerald-600 dark:text-emerald-400">{{ __('Revenue') }} {{ Report::formatMinor($totals['revenue_minor'], $currency) }}</span>
                <span class="text-red-600 dark:text-red-400">{{ __('Expenses') }} {{ Report::formatMinor($totals['expense_minor'], $currency) }}</span>
                <span class="font-medium">{{ __('Profit') }} {{ Report::formatMinor($totals['profit_minor'], $currency) }}</span>
            </div>
        @endforeach
    </flux:card>

    <flux:heading size="lg" class="mt-10">{{ __('Servers') }}</flux:heading>
    <flux:subheading class="mt-1">{{ __('Monthly infrastructure cost (allocated to active services above)') }}</flux:subheading>

    <flux:card class="mt-4 overflow-hidden p-0">
        <flux:table container:class="px-4 sm:px-6 py-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Server') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Monthly cost') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Active services') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Unallocated') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($report['servers'] as $row)
                    <flux:table.row :key="'server-'.$row['id']">
                        <flux:table.cell>
                            <a href="{{ $row['edit_url'] }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $row['label'] }}</a>
                            @if ($row['detail'])
                                <flux:text class="mt-0.5 text-xs">{{ $row['detail'] }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ Report::formatMinor($row['monthly_cost_minor'], $row['currency']) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $row['active_services'] }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($row['unallocated_minor'] > 0)
                                <span class="text-amber-600 dark:text-amber-400">{{ Report::formatMinor($row['unallocated_minor'], $row['currency']) }}</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No servers configured.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</section>
