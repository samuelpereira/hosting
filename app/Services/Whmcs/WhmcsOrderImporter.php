<?php

namespace App\Services\Whmcs;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\TransactionMethod;
use App\Enums\TransactionStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhmcsOrderImporter
{
    /** @var array<int, int> */
    private array $clientIdByWhmcsId = [];

    /** @var array<int, string> */
    private array $clientCurrencyById = [];

    /** @var array<int, int> */
    private array $serviceIdByWhmcsId = [];

    public function __construct(
        private readonly string $connectionName,
        private readonly string $invoicesTable,
        private readonly string $invoiceItemsTable,
        private readonly array $invoiceColumns,
        private readonly array $invoiceItemColumns,
        private readonly string $defaultCurrency,
        private readonly bool $importItems,
        private readonly bool $importPayments,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('whmcs-import');

        return new self(
            connectionName: $config['connection'],
            invoicesTable: $config['invoices_table'],
            invoiceItemsTable: $config['invoice_items_table'],
            invoiceColumns: $config['invoice_columns'],
            invoiceItemColumns: $config['invoice_item_columns'],
            defaultCurrency: $config['default_currency'],
            importItems: true,
            importPayments: true,
        );
    }

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    /**
     * @param  list<string>|null  $statusFilter  WHMCS status values to include, or null for all
     */
    public function whmcsOrderCount(?array $statusFilter = null): int
    {
        if (! $this->tableExists($this->invoicesTable)) {
            return 0;
        }

        return (int) $this->baseQuery($statusFilter)->count();
    }

    /**
     * @param  list<string>|null  $statusFilter
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(
        bool $dryRun = false,
        ?int $limit = null,
        ?int $offset = null,
        ?array $statusFilter = null,
    ): array {
        $this->loadMaps();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $query = $this->baseQuery($statusFilter)->orderBy($this->invoiceColumns['id']);

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(100, function ($rows) use ($dryRun, $statusFilter, &$stats) {
            $whmcsOrderIds = $rows->pluck($this->invoiceColumns['id'])->map(fn ($id) => (int) $id)->filter()->all();
            $itemsByOrder = $this->loadItemsForOrders($whmcsOrderIds);

            foreach ($rows as $row) {
                $whmcsId = (int) ($row->{$this->invoiceColumns['id']} ?? 0);
                $whmcsUserId = (int) ($row->{$this->invoiceColumns['userid']} ?? 0);

                if ($whmcsId <= 0) {
                    $stats['skipped']++;

                    continue;
                }

                if ($statusFilter !== null && ! $this->matchesStatusFilter($row, $statusFilter)) {
                    $stats['skipped']++;

                    continue;
                }

                if ($whmcsUserId <= 0) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS invoice #{$whmcsId}: missing userid";

                    continue;
                }

                $clientId = $this->clientIdByWhmcsId[$whmcsUserId] ?? null;
                if ($clientId === null) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS invoice #{$whmcsId}: no client with whmcs_id {$whmcsUserId}";

                    continue;
                }

                try {
                    $payload = $this->mapOrderRow($row, $clientId, $whmcsId);
                } catch (\Throwable $e) {
                    $stats['errors'][] = "WHMCS invoice #{$whmcsId}: {$e->getMessage()}";
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    Order::query()->where('whmcs_id', $whmcsId)->exists()
                        ? $stats['updated']++
                        : $stats['created']++;

                    continue;
                }

                $invoice = Order::query()->updateOrCreate(
                    ['whmcs_id' => $whmcsId],
                    $payload,
                );

                if ($this->importItems && $this->tableExists($this->invoiceItemsTable)) {
                    $this->syncOrderItems(
                        $invoice,
                        $itemsByOrder->get($whmcsId, collect()),
                    );
                }

                if ($this->importPayments && $invoice->status === OrderStatus::Paid && $invoice->paid_at !== null) {
                    $this->syncPaymentTransaction($invoice, $row, $whmcsId);
                }

                $invoice->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
            }
        }, $this->invoiceColumns['id']);

        return $stats;
    }

    /**
     * @param  list<string>|null  $statusFilter
     */
    private function baseQuery(?array $statusFilter)
    {
        $query = $this->connection()->table($this->invoicesTable);

        if ($statusFilter !== null && $statusFilter !== [] && isset($this->invoiceColumns['status'])) {
            $query->whereIn($this->invoiceColumns['status'], $statusFilter);
        }

        return $query;
    }

    /**
     * @param  list<string>  $statusFilter
     */
    private function matchesStatusFilter(object $row, array $statusFilter): bool
    {
        if (! isset($this->invoiceColumns['status'])) {
            return false;
        }

        $status = trim((string) ($row->{$this->invoiceColumns['status']} ?? ''));

        return in_array($status, $statusFilter, true);
    }

    private function loadMaps(): void
    {
        $clients = Client::query()->whereNotNull('whmcs_id')->get(['id', 'whmcs_id', 'currency']);

        $this->clientIdByWhmcsId = [];
        $this->clientCurrencyById = [];

        foreach ($clients as $client) {
            $this->clientIdByWhmcsId[(int) $client->whmcs_id] = (int) $client->id;
            $this->clientCurrencyById[(int) $client->id] = strtoupper((string) $client->currency);
        }

        $this->serviceIdByWhmcsId = Service::query()
            ->whereNotNull('whmcs_id')
            ->pluck('id', 'whmcs_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $whmcsOrderIds
     * @return Collection<int, Collection<int, object>>
     */
    private function loadItemsForOrders(array $whmcsOrderIds): Collection
    {
        if (! $this->importItems || $whmcsOrderIds === [] || ! $this->tableExists($this->invoiceItemsTable)) {
            return collect();
        }

        $invoiceIdColumn = $this->invoiceItemColumns['invoiceid'];

        return $this->connection()
            ->table($this->invoiceItemsTable)
            ->whereIn(DB::raw("CAST({$invoiceIdColumn} AS UNSIGNED)"), $whmcsOrderIds)
            ->orderBy($this->invoiceItemColumns['id'])
            ->get()
            ->groupBy(fn (object $row) => (int) trim((string) ($row->{$invoiceIdColumn} ?? 0)));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOrderRow(object $row, int $clientId, int $whmcsId): array
    {
        $currency = $this->clientCurrencyById[$clientId] ?? strtoupper($this->defaultCurrency);
        $subtotalMinor = $this->decimalToMinor($row->{$this->invoiceColumns['subtotal']} ?? 0);
        $taxMinor = $this->decimalToMinor($row->{$this->invoiceColumns['tax']} ?? 0);
        $totalMinor = $this->decimalToMinor($row->{$this->invoiceColumns['total']} ?? 0);

        if ($totalMinor <= 0 && $subtotalMinor > 0) {
            $totalMinor = $subtotalMinor + $taxMinor;
        }

        $issuedAt = $this->parseDate($row->{$this->invoiceColumns['date']} ?? null);
        $dueAt = $this->parseDate($row->{$this->invoiceColumns['duedate']} ?? null);
        $paidAt = $this->parseDateTime($row->{$this->invoiceColumns['datepaid']} ?? null);
        $status = $this->mapStatus((string) ($row->{$this->invoiceColumns['status']} ?? ''));

        if ($status === OrderStatus::Paid && $paidAt === null) {
            $paidAt = $dueAt ?? $issuedAt;
        }

        $number = $this->resolveOrderNumber($row, $whmcsId);

        return [
            'whmcs_id' => $whmcsId,
            'client_id' => $clientId,
            'number' => $number,
            'status' => $status,
            'currency' => $currency,
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'irs_minor' => 0,
            'total_minor' => $totalMinor > 0 ? $totalMinor : $subtotalMinor + $taxMinor,
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'paid_at' => $status === OrderStatus::Paid ? $paidAt : null,
            'notes' => $this->nullableString($row->{$this->invoiceColumns['notes']} ?? null),
        ];
    }

    private function resolveOrderNumber(object $row, int $whmcsId): string
    {
        if (isset($this->invoiceColumns['invoicenum'])) {
            $custom = trim((string) ($row->{$this->invoiceColumns['invoicenum']} ?? ''));
            if ($custom !== '') {
                return Str::limit($custom, 64, '');
            }
        }

        return 'WHMCS-'.$whmcsId;
    }

    private function syncOrderItems(Order $invoice, Collection $itemRows): void
    {
        $keptItemIds = [];
        $position = 0;

        foreach ($itemRows as $itemRow) {
            $whmcsItemId = (int) ($itemRow->{$this->invoiceItemColumns['id']} ?? 0);
            if ($whmcsItemId <= 0) {
                continue;
            }

            $relId = (int) ($itemRow->{$this->invoiceItemColumns['relid']} ?? 0);
            $whmcsType = (string) ($itemRow->{$this->invoiceItemColumns['type']} ?? '');
            $serviceId = $this->resolveServiceId($whmcsType, $relId);

            $item = OrderItem::query()->updateOrCreate(
                ['whmcs_id' => $whmcsItemId],
                [
                    'order_id' => $invoice->id,
                    'position' => $position,
                    'type' => $serviceId !== null ? OrderItemType::Service : OrderItemType::Manual,
                    'description' => Str::limit(
                        trim((string) ($itemRow->{$this->invoiceItemColumns['description']} ?? '')) ?: 'Line item',
                        65535,
                        '',
                    ),
                    'quantity' => 1,
                    'unit_amount_minor' => $this->decimalToMinor($itemRow->{$this->invoiceItemColumns['amount']} ?? 0),
                    'tax_amount_minor' => 0,
                    'service_id' => $serviceId,
                ],
            );

            $keptItemIds[] = $item->id;
            $position++;
        }

        OrderItem::query()
            ->where('order_id', $invoice->id)
            ->whereNotNull('whmcs_id')
            ->whereNotIn('id', $keptItemIds)
            ->delete();
    }

    private function syncPaymentTransaction(Order $invoice, object $row, int $whmcsId): void
    {
        $whmcsMethod = $this->nullableString($row->{$this->invoiceColumns['paymentmethod']} ?? null);

        Transaction::query()->updateOrCreate(
            ['order_id' => $invoice->id, 'reference' => 'whmcs-order-'.$whmcsId],
            [
                'status' => TransactionStatus::Completed,
                'method' => TransactionMethod::tryFromWhmcs($whmcsMethod),
                'currency' => $invoice->currency,
                'amount_minor' => (int) $invoice->total_minor,
                'paid_at' => $invoice->paid_at,
                'notes' => null,
            ],
        );
    }

    private function resolveServiceId(string $whmcsType, int $relId): ?int
    {
        if ($relId <= 0) {
            return null;
        }

        $type = strtolower(trim($whmcsType));

        if (! in_array($type, ['hosting', 'hostingaccount', 'upgrade'], true)) {
            return null;
        }

        return $this->serviceIdByWhmcsId[$relId] ?? null;
    }

    private function mapStatus(string $whmcsStatus): OrderStatus
    {
        return match (strtolower(trim($whmcsStatus))) {
            'paid' => OrderStatus::Paid,
            'draft' => OrderStatus::Draft,
            'cancelled', 'canceled', 'refunded' => OrderStatus::Canceled,
            default => OrderStatus::Unpaid,
        };
    }

    private function decimalToMinor(mixed $value): int
    {
        return max(0, (int) round((float) $value * 100));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function tableExists(string $table): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($table);
    }
}
