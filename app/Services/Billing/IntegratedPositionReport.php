<?php

namespace App\Services\Billing;

use App\Enums\DomainStatus;
use App\Enums\ServiceStatus;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Service;

class IntegratedPositionReport
{
    /**
     * @return array{
     *     products: list<array<string, mixed>>,
     *     domains: list<array<string, mixed>>,
     *     servers: list<array<string, mixed>>,
     *     summary_by_currency: array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>,
     *     summary_yearly_by_currency: array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>,
     *     products_totals_by_currency: array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>,
     *     domains_yearly_totals_by_currency: array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>,
     *     domains_totals_by_currency: array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>,
     * }
     */
    public function build(): array
    {
        $activeServicesPerServer = Service::query()
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('server_id')
            ->where('recurring_amount_minor', '>', 0)
            ->selectRaw('server_id, count(*) as service_count')
            ->groupBy('server_id')
            ->pluck('service_count', 'server_id');

        $serversById = Server::query()
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        /** @var array<string, array<string, mixed>> $productGroups */
        $productGroups = [];

        Service::query()
            ->with(['product', 'server'])
            ->where('status', ServiceStatus::Active)
            ->orderBy('product_id')
            ->orderBy('id')
            ->get()
            ->each(function (Service $service) use ($activeServicesPerServer, $serversById, &$productGroups): void {
                $revenueMinor = $service->monthlyAmountMinor();
                $expenseMinor = 0;

                if ($service->server_id !== null && $serversById->has($service->server_id)) {
                    $server = $serversById->get($service->server_id);
                    $monthlyCost = (int) ($server->cost_amount_minor ?? 0);
                    if ($monthlyCost > 0 && (int) ($service->recurring_amount_minor ?? 0) > 0) {
                        $count = max(1, (int) ($activeServicesPerServer[$service->server_id] ?? 1));
                        $expenseMinor = (int) round($monthlyCost / $count);
                    }
                }

                $currency = $service->currency ?: 'EUR';
                $productId = (int) ($service->product_id ?? 0);
                $groupKey = $productId.'|'.$currency;

                if (! isset($productGroups[$groupKey])) {
                    $productGroups[$groupKey] = [
                        'id' => $productId > 0 ? $productId : null,
                        'label' => $service->product?->name ?? __('Services without product'),
                        'detail' => '',
                        'service_count' => 0,
                        'currency' => $currency,
                        'revenue_minor' => 0,
                        'expense_minor' => 0,
                        'profit_minor' => 0,
                        'edit_url' => $productId > 0
                            ? route('billing.products.show', $productId)
                            : route('billing.services.index'),
                    ];
                }

                $productGroups[$groupKey]['service_count']++;
                $productGroups[$groupKey]['revenue_minor'] += $revenueMinor;
                $productGroups[$groupKey]['expense_minor'] += $expenseMinor;
                $productGroups[$groupKey]['profit_minor'] += $revenueMinor - $expenseMinor;
            });

        $productRows = collect($productGroups)
            ->map(function (array $row): array {
                $count = (int) $row['service_count'];
                $row['detail'] = trans_choice(':count active service|:count active services', $count, ['count' => $count]);

                return $row;
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $domainRows = Domain::query()
            ->with(['client', 'registrar', 'registrarTldPrice', 'registrarTld'])
            ->where('status', DomainStatus::Active)
            ->orderBy('fqdn')
            ->get()
            ->map(function (Domain $domain): array {
                $revenueYearlyMinor = $domain->yearlyRevenueMinor();
                $expenseYearlyMinor = $domain->yearlyCostMinor();
                $revenueMonthlyMinor = $domain->monthlyRevenueMinor();
                $expenseMonthlyMinor = $domain->monthlyCostMinor();
                $currency = $domain->currency ?: 'EUR';

                return [
                    'id' => $domain->id,
                    'label' => $domain->fqdn,
                    'detail' => trim(collect([
                        $domain->client?->name,
                        $domain->registrar?->name,
                    ])->filter()->implode(' · ')),
                    'currency' => $currency,
                    'revenue_yearly_minor' => $revenueYearlyMinor,
                    'expense_yearly_minor' => $expenseYearlyMinor,
                    'profit_yearly_minor' => $revenueYearlyMinor - $expenseYearlyMinor,
                    'revenue_minor' => $revenueMonthlyMinor,
                    'expense_minor' => $expenseMonthlyMinor,
                    'profit_minor' => $revenueMonthlyMinor - $expenseMonthlyMinor,
                    'edit_url' => route('billing.domains.edit', $domain),
                ];
            })
            ->values()
            ->all();

        $serverRows = $serversById
            ->values()
            ->map(function (Server $server) use ($activeServicesPerServer): array {
                $monthlyCost = (int) ($server->cost_amount_minor ?? 0);
                $activeCount = (int) ($activeServicesPerServer[$server->id] ?? 0);
                $allocatedMinor = $activeCount > 0 && $monthlyCost > 0
                    ? $monthlyCost
                    : 0;
                $unallocatedMinor = max(0, $monthlyCost - $allocatedMinor);

                return [
                    'id' => $server->id,
                    'label' => $server->name,
                    'detail' => $server->hostname,
                    'currency' => $server->cost_currency ?: 'EUR',
                    'monthly_cost_minor' => $monthlyCost,
                    'active_services' => $activeCount,
                    'allocated_minor' => $allocatedMinor,
                    'unallocated_minor' => $unallocatedMinor,
                    'edit_url' => route('billing.servers.edit', $server),
                ];
            })
            ->values()
            ->all();

        $productsTotals = $this->totalsByCurrency($productRows);
        $productsYearlyTotals = $this->annualizeMonthlyTotals($productsTotals);
        $domainsYearlyTotals = $this->totalsByCurrency($domainRows, 'yearly');
        $domainsMonthlyTotals = $this->totalsByCurrency($domainRows);
        $summaryByCurrency = $this->mergeCurrencyTotals($productsTotals, $domainsMonthlyTotals);
        $summaryYearlyByCurrency = $this->mergeCurrencyTotals($productsYearlyTotals, $domainsYearlyTotals);

        return [
            'products' => $productRows,
            'domains' => $domainRows,
            'servers' => $serverRows,
            'products_totals_by_currency' => $productsTotals,
            'domains_yearly_totals_by_currency' => $domainsYearlyTotals,
            'domains_totals_by_currency' => $domainsMonthlyTotals,
            'summary_by_currency' => $summaryByCurrency,
            'summary_yearly_by_currency' => $summaryYearlyByCurrency,
        ];
    }

    public static function formatMinor(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2, ',', '.').' '.$currency;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>
     */
    private function totalsByCurrency(array $rows, string $period = 'monthly'): array
    {
        $suffix = $period === 'yearly' ? '_yearly_minor' : '_minor';
        $totals = [];

        foreach ($rows as $row) {
            $currency = $row['currency'];
            $totals[$currency] ??= ['revenue_minor' => 0, 'expense_minor' => 0, 'profit_minor' => 0];
            $totals[$currency]['revenue_minor'] += (int) $row['revenue'.$suffix];
            $totals[$currency]['expense_minor'] += (int) $row['expense'.$suffix];
            $totals[$currency]['profit_minor'] += (int) $row['profit'.$suffix];
        }

        ksort($totals);

        return $totals;
    }

    /**
     * @param  array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>  $monthlyTotals
     * @return array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>
     */
    private function annualizeMonthlyTotals(array $monthlyTotals): array
    {
        $yearly = [];

        foreach ($monthlyTotals as $currency => $totals) {
            $yearly[$currency] = [
                'revenue_minor' => (int) $totals['revenue_minor'] * 12,
                'expense_minor' => (int) $totals['expense_minor'] * 12,
                'profit_minor' => (int) $totals['profit_minor'] * 12,
            ];
        }

        ksort($yearly);

        return $yearly;
    }

    /**
     * @param  array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>  ...$parts
     * @return array<string, array{revenue_minor: int, expense_minor: int, profit_minor: int}>
     */
    private function mergeCurrencyTotals(array ...$parts): array
    {
        $merged = [];

        foreach ($parts as $part) {
            foreach ($part as $currency => $values) {
                $merged[$currency] ??= ['revenue_minor' => 0, 'expense_minor' => 0, 'profit_minor' => 0];
                $merged[$currency]['revenue_minor'] += $values['revenue_minor'];
                $merged[$currency]['expense_minor'] += $values['expense_minor'];
                $merged[$currency]['profit_minor'] += $values['profit_minor'];
            }
        }

        ksort($merged);

        return $merged;
    }
}
