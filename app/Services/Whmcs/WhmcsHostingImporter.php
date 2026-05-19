<?php

namespace App\Services\Whmcs;

use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhmcsHostingImporter
{
    /** @var array<int, int> */
    private array $clientIdByWhmcsId = [];

    /** @var array<int, int> */
    private array $productIdByWhmcsId = [];

    /** @var array<int, int> WHMCS package id → app product (from map; supports shared slugs) */
    private array $productIdByWhmcsPackageId = [];

    /** @var array<string, int> clientId:fqdn → domain id */
    private array $domainIdByClientAndFqdn = [];

    private WhmcsProductMap $productMap;

    public function __construct(
        private readonly string $connectionName,
        private readonly string $hostingTable,
        private readonly string $productsTable,
        private readonly array $columns,
        private readonly array $productColumns,
        private readonly ?int $defaultProductId,
        ?WhmcsProductMap $productMap = null,
    ) {
        $this->productMap = $productMap ?? WhmcsProductMap::fromConfig();
    }

    public static function fromConfig(): self
    {
        $config = config('whmcs-import');
        $defaultProductId = $config['default_product_id'];

        return new self(
            connectionName: $config['connection'],
            hostingTable: $config['hosting_table'],
            productsTable: $config['products_table'],
            columns: $config['hosting_columns'],
            productColumns: $config['product_columns'],
            defaultProductId: $defaultProductId !== null && $defaultProductId !== '' ? (int) $defaultProductId : null,
            productMap: WhmcsProductMap::fromConfig(),
        );
    }

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function whmcsHostingCount(): int
    {
        if (! $this->tableExists($this->hostingTable)) {
            return 0;
        }

        return (int) $this->connection()->table($this->hostingTable)->count();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(bool $dryRun = false, ?int $limit = null, ?int $offset = null): array
    {
        $this->loadMaps();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $query = $this->connection()->table($this->hostingTable)->orderBy($this->columns['id']);

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(100, function ($rows) use ($dryRun, &$stats) {
            foreach ($rows as $row) {
                $whmcsId = (int) ($row->{$this->columns['id']} ?? 0);
                $whmcsUserId = (int) ($row->{$this->columns['userid']} ?? 0);

                if ($whmcsId <= 0) {
                    $stats['skipped']++;

                    continue;
                }

                if ($whmcsUserId <= 0) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS hosting #{$whmcsId}: missing userid";

                    continue;
                }

                $clientId = $this->clientIdByWhmcsId[$whmcsUserId] ?? null;
                if ($clientId === null) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS hosting #{$whmcsId}: no client with whmcs_id {$whmcsUserId}";

                    continue;
                }

                $packageId = (int) ($row->{$this->columns['packageid']} ?? 0);
                $productId = $this->resolveProductId($packageId, $whmcsId, $stats);
                if ($productId === null) {
                    continue;
                }

                try {
                    $payload = $this->mapRow($row, $clientId, $productId);
                } catch (\Throwable $e) {
                    $stats['errors'][] = "WHMCS hosting #{$whmcsId}: {$e->getMessage()}";
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    Service::query()->where('whmcs_id', $whmcsId)->exists()
                        ? $stats['updated']++
                        : $stats['created']++;

                    continue;
                }

                $service = Service::query()->updateOrCreate(
                    ['whmcs_id' => $whmcsId],
                    $payload,
                );

                $service->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
            }
        }, $this->columns['id']);

        return $stats;
    }

    private function loadMaps(): void
    {
        $this->clientIdByWhmcsId = Client::query()
            ->whereNotNull('whmcs_id')
            ->pluck('id', 'whmcs_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->productIdByWhmcsId = Product::query()
            ->whereNotNull('whmcs_id')
            ->pluck('id', 'whmcs_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->loadProductLinksFromWhmcsCatalog();

        $this->domainIdByClientAndFqdn = [];
        Domain::query()
            ->select(['id', 'client_id', 'fqdn'])
            ->get()
            ->each(function (Domain $domain) {
                $key = $domain->client_id.':'.strtolower($domain->fqdn);
                $this->domainIdByClientAndFqdn[$key] = (int) $domain->id;
            });
    }

    private function loadProductLinksFromWhmcsCatalog(): void
    {
        if ($this->productMap->isEmpty() || ! $this->tableExists($this->productsTable)) {
            return;
        }

        $rows = $this->connection()
            ->table($this->productsTable)
            ->get([
                $this->productColumns['id'],
                $this->productColumns['name'],
            ]);

        foreach ($rows as $row) {
            $whmcsProductId = (int) ($row->{$this->productColumns['id']} ?? 0);
            if ($whmcsProductId <= 0) {
                continue;
            }

            $name = trim((string) ($row->{$this->productColumns['name']} ?? ''));
            $slug = $this->productMap->slugForPackage($whmcsProductId, $name);
            if ($slug === null) {
                continue;
            }

            $product = Product::query()->where('slug', $slug)->first();
            if ($product === null) {
                continue;
            }

            $this->productIdByWhmcsPackageId[$whmcsProductId] = (int) $product->id;

            if (isset($this->productIdByWhmcsId[$whmcsProductId])) {
                continue;
            }

            if ($product->whmcs_id === null) {
                $product->whmcs_id = $whmcsProductId;
                $product->save();
                $this->productIdByWhmcsId[$whmcsProductId] = (int) $product->id;
            } elseif ((int) $product->whmcs_id === $whmcsProductId) {
                $this->productIdByWhmcsId[$whmcsProductId] = (int) $product->id;
            }
        }
    }

    /**
     * @param  array{created: int, updated: int, skipped: int, errors: list<string>}  $stats
     */
    private function resolveProductId(int $packageId, int $whmcsHostingId, array &$stats): ?int
    {
        if ($packageId > 0 && isset($this->productIdByWhmcsId[$packageId])) {
            return $this->productIdByWhmcsId[$packageId];
        }

        if ($packageId > 0 && isset($this->productIdByWhmcsPackageId[$packageId])) {
            return $this->productIdByWhmcsPackageId[$packageId];
        }

        if ($this->defaultProductId !== null && Product::query()->whereKey($this->defaultProductId)->exists()) {
            if ($packageId > 0) {
                $stats['errors'][] = "WHMCS hosting #{$whmcsHostingId}: unknown packageid {$packageId}, using default product";
            }

            return $this->defaultProductId;
        }

        $stats['skipped']++;
        $stats['errors'][] = $packageId > 0
            ? "WHMCS hosting #{$whmcsHostingId}: unknown packageid {$packageId} (add to config/whmcs-product-map.php, run --sync-products, or set WHMCS_IMPORT_DEFAULT_PRODUCT_ID)"
            : "WHMCS hosting #{$whmcsHostingId}: missing packageid";

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(object $row, int $clientId, int $productId): array
    {
        $product = Product::query()->findOrFail($productId);
        $client = Client::query()->findOrFail($clientId);

        $domainName = strtolower(trim((string) ($row->{$this->columns['domain']} ?? '')));
        $associatedDomain = $domainName !== '' ? Str::limit($domainName, 255, '') : null;

        $domainId = null;
        if ($associatedDomain !== null) {
            $key = $clientId.':'.$associatedDomain;
            $domainId = $this->domainIdByClientAndFqdn[$key] ?? null;
        }

        $currency = strtoupper(strlen($client->currency) === 3 ? $client->currency : config('whmcs-import.default_currency', 'EUR'));
        $recurringAmount = (float) ($row->{$this->columns['amount']} ?? 0);
        $firstPaymentAmount = isset($this->columns['firstpaymentamount'])
            ? (float) ($row->{$this->columns['firstpaymentamount']} ?? 0)
            : $recurringAmount;

        $diskLimit = isset($this->columns['disklimit']) ? (int) ($row->{$this->columns['disklimit']} ?? 0) : 0;
        $allocatedDiskGb = $this->resolveDiskGb($diskLimit, $product->default_allocated_disk_gb);

        $billingCycle = isset($this->columns['billingcycle'])
            ? (string) ($row->{$this->columns['billingcycle']} ?? '')
            : '';
        [, $intervalUnit, $intervalCount] = WhmcsBillingCycle::parse($billingCycle);

        return [
            'whmcs_id' => (int) $row->{$this->columns['id']},
            'client_id' => $clientId,
            'product_id' => $productId,
            'username' => $this->nullableString(
                isset($this->columns['username']) ? ($row->{$this->columns['username']} ?? null) : null,
            ),
            'password' => $this->nullableString(
                isset($this->columns['password']) ? ($row->{$this->columns['password']} ?? null) : null,
            ),
            'currency' => $currency,
            'first_payment_amount_minor' => $this->decimalToMinor($firstPaymentAmount),
            'recurring_amount_minor' => $this->decimalToMinor($recurringAmount),
            'interval_unit' => $intervalUnit,
            'interval_count' => max(1, $intervalCount),
            'server_id' => $this->resolveServerId($row),
            'domain_id' => $domainId,
            'associated_domain' => $associatedDomain,
            'status' => $this->mapStatus((string) ($row->{$this->columns['domainstatus']} ?? '')),
            'activated_at' => $this->parseDate($row->{$this->columns['regdate']} ?? null),
            'due_at' => $this->parseDate($row->{$this->columns['nextduedate']} ?? null),
            'cancelled_at' => $this->resolveCancelledAt($row),
            'allocated_ram_mb' => $product->default_allocated_ram_mb,
            'allocated_disk_gb' => $allocatedDiskGb,
            'notes' => $this->nullableString($row->{$this->columns['notes']} ?? null),
        ];
    }

    private function decimalToMinor(float $amount): ?int
    {
        if ($amount <= 0) {
            return null;
        }

        return max(0, (int) round($amount * 100));
    }

    private function resolveDiskGb(int $diskLimitMb, ?int $productDefault): ?int
    {
        if ($diskLimitMb > 0) {
            return max(1, (int) round($diskLimitMb / 1024));
        }

        return $productDefault;
    }

    private function resolveServerId(object $row): ?int
    {
        if (! isset($this->columns['server'])) {
            return null;
        }

        $value = trim((string) ($row->{$this->columns['server']} ?? ''));
        if ($value === '' || $value === '0') {
            return null;
        }

        $serverId = Server::query()
            ->where('hostname', $value)
            ->orWhere('name', $value)
            ->value('id');

        return $serverId !== null ? (int) $serverId : null;
    }

    private function mapStatus(string $whmcsStatus): ServiceStatus
    {
        return match (strtolower(trim($whmcsStatus))) {
            'active' => ServiceStatus::Active,
            'suspended' => ServiceStatus::Suspended,
            'terminated', 'cancelled', 'fraud', 'completed' => ServiceStatus::Cancelled,
            default => ServiceStatus::Pending,
        };
    }

    private function resolveCancelledAt(object $row): ?Carbon
    {
        if (isset($this->columns['termination_date'])) {
            $terminated = $this->parseDate($row->{$this->columns['termination_date']} ?? null);
            if ($terminated !== null) {
                return $terminated;
            }
        }

        $status = strtolower(trim((string) ($row->{$this->columns['domainstatus']} ?? '')));
        if (in_array($status, ['terminated', 'cancelled'], true)) {
            return $this->parseDate($row->{$this->columns['nextduedate']} ?? null)
                ?? $this->parseDate($row->{$this->columns['regdate']} ?? null);
        }

        return null;
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
