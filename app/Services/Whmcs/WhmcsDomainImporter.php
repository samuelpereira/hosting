<?php

namespace App\Services\Whmcs;

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\Service;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhmcsDomainImporter
{
    /** @var array<int, int> */
    private array $clientIdByWhmcsId = [];

    /** @var array<string, int> registrar code → id */
    private array $registrarIdByCode = [];

    /** @var array<string, int> "{registrarId}:{normalizedTld}" → registrar_tld id */
    private array $registrarTldIdByRegistrarAndTld = [];

    /** @var array<string, int> normalized TLD → registrar_tld id (first match) */
    private array $registrarTldIdByTld = [];

    public function __construct(
        private readonly string $connectionName,
        private readonly string $domainsTable,
        private readonly array $columns,
        private readonly ?int $defaultRegistrarId,
        private readonly array $activeStatuses,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('whmcs-import');
        $defaultRegistrarId = $config['default_registrar_id'];

        return new self(
            connectionName: $config['connection'],
            domainsTable: $config['domains_table'],
            columns: $config['domain_columns'],
            defaultRegistrarId: $defaultRegistrarId !== null && $defaultRegistrarId !== ''
                ? (int) $defaultRegistrarId
                : null,
            activeStatuses: $config['domain_active_statuses'],
        );
    }

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function whmcsDomainCount(bool $activeOnly = false): int
    {
        if (! $this->tableExists($this->domainsTable)) {
            return 0;
        }

        return (int) $this->baseQuery($activeOnly)->count();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(
        bool $dryRun = false,
        ?int $limit = null,
        ?int $offset = null,
        bool $activeOnly = false,
    ): array {
        $this->loadClientMap();
        $this->loadRegistrarMap();
        $this->loadRegistrarTldMap();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $query = $this->baseQuery($activeOnly)->orderBy($this->columns['id']);

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(100, function ($rows) use ($dryRun, $activeOnly, &$stats) {
            foreach ($rows as $row) {
                $whmcsId = (int) ($row->{$this->columns['id']} ?? 0);
                $whmcsUserId = (int) ($row->{$this->columns['userid']} ?? 0);

                if ($whmcsId <= 0) {
                    $stats['skipped']++;

                    continue;
                }

                if ($activeOnly && ! $this->isActiveStatus($row)) {
                    $stats['skipped']++;

                    continue;
                }

                if ($whmcsUserId <= 0) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS domain #{$whmcsId}: missing userid";

                    continue;
                }

                $clientId = $this->clientIdByWhmcsId[$whmcsUserId] ?? null;
                if ($clientId === null) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS domain #{$whmcsId}: no client with whmcs_id {$whmcsUserId}";

                    continue;
                }

                $fqdn = $this->normalizeFqdn((string) ($row->{$this->columns['domain']} ?? ''));
                if ($fqdn === '') {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS domain #{$whmcsId}: missing domain name";

                    continue;
                }

                try {
                    $payload = $this->mapRow($row, $clientId, $fqdn, $whmcsId);
                } catch (\Throwable $e) {
                    $stats['errors'][] = "WHMCS domain #{$whmcsId}: {$e->getMessage()}";
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    Domain::query()
                        ->where('client_id', $clientId)
                        ->where('fqdn', $fqdn)
                        ->exists()
                        ? $stats['updated']++
                        : $stats['created']++;

                    continue;
                }

                // WHMCS may have multiple tbldomains rows for the same name (renewals, cancelled, etc.).
                $domain = Domain::query()->updateOrCreate(
                    [
                        'client_id' => $clientId,
                        'fqdn' => $fqdn,
                    ],
                    $payload,
                );

                $this->linkServicesToDomain($domain);

                $domain->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
            }
        }, $this->columns['id']);

        return $stats;
    }

    private function baseQuery(bool $activeOnly)
    {
        $query = $this->connection()->table($this->domainsTable);

        if ($activeOnly && isset($this->columns['status'])) {
            $query->whereIn($this->columns['status'], $this->activeStatuses);
        }

        return $query;
    }

    private function isActiveStatus(object $row): bool
    {
        if (! isset($this->columns['status'])) {
            return true;
        }

        $status = trim((string) ($row->{$this->columns['status']} ?? ''));

        return in_array($status, $this->activeStatuses, true);
    }

    private function loadClientMap(): void
    {
        $this->clientIdByWhmcsId = Client::query()
            ->whereNotNull('whmcs_id')
            ->pluck('id', 'whmcs_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function loadRegistrarMap(): void
    {
        $this->registrarIdByCode = Registrar::query()
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function loadRegistrarTldMap(): void
    {
        $this->registrarTldIdByRegistrarAndTld = [];
        $this->registrarTldIdByTld = [];

        foreach (RegistrarTld::query()->get(['id', 'registrar_id', 'tld']) as $row) {
            $normalized = RegistrarTld::normalizeTld($row->tld);
            $this->registrarTldIdByRegistrarAndTld["{$row->registrar_id}:{$normalized}"] = (int) $row->id;
            $this->registrarTldIdByTld[$normalized] ??= (int) $row->id;
        }
    }

    private function resolveRegistrarTldId(int $registrarId, string $extractedTld): int
    {
        $normalized = RegistrarTld::normalizeTld($extractedTld);
        $scopedKey = "{$registrarId}:{$normalized}";

        if (isset($this->registrarTldIdByRegistrarAndTld[$scopedKey])) {
            return $this->registrarTldIdByRegistrarAndTld[$scopedKey];
        }

        if (isset($this->registrarTldIdByTld[$normalized])) {
            return $this->registrarTldIdByTld[$normalized];
        }

        $targetRegistrarId = $this->defaultRegistrarIdForNewTlds();

        $created = RegistrarTld::query()->create([
            'registrar_id' => $targetRegistrarId,
            'tld' => $normalized,
        ]);

        $id = (int) $created->id;
        $targetKey = "{$targetRegistrarId}:{$normalized}";
        $this->registrarTldIdByRegistrarAndTld[$targetKey] = $id;
        $this->registrarTldIdByTld[$normalized] = $id;

        return $id;
    }

    private function defaultRegistrarIdForNewTlds(): int
    {
        if ($this->defaultRegistrarId !== null && Registrar::query()->whereKey($this->defaultRegistrarId)->exists()) {
            return $this->defaultRegistrarId;
        }

        throw new \RuntimeException(
            'Cannot create TLD during import: set WHMCS_IMPORT_DEFAULT_REGISTRAR_ID to an existing registrar.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(object $row, int $clientId, string $fqdn, int $whmcsId): array
    {
        $registrarId = $this->resolveRegistrarId((string) ($row->{$this->columns['registrar']} ?? ''));
        $tld = $this->extractTld($fqdn);

        $registrarTldId = $tld !== null
            ? $this->resolveRegistrarTldId($registrarId, $tld)
            : null;

        [$intervalUnit, $intervalCount] = $this->resolveBillingInterval($registrarTldId);

        $registeredAt = $this->parseDate($row->{$this->columns['registrationdate']} ?? null)
            ?? now()->startOfDay();
        $expiresAt = $this->parseDate($row->{$this->columns['expirydate']} ?? null)
            ?? $this->parseDate($row->{$this->columns['nextduedate']} ?? null)
            ?? $registeredAt->copy()->addYear();
        $dueAt = $this->parseDate($row->{$this->columns['nextduedate']} ?? null) ?? $expiresAt;

        $whmcsStatus = isset($this->columns['status'])
            ? trim((string) ($row->{$this->columns['status']} ?? ''))
            : '';
        $currency = Client::query()->whereKey($clientId)->value('currency')
            ?? config('whmcs-import.default_currency', 'EUR');

        return [
            'whmcs_id' => $whmcsId,
            'client_id' => $clientId,
            'registrar_id' => $registrarId,
            'registrar_tld_id' => $registrarTldId,
            'registrar_tld_price_id' => null,
            'fqdn' => $fqdn,
            'status' => DomainStatus::tryFromWhmcs($whmcsStatus)
                ?? ($whmcsStatus === '' ? DomainStatus::Active : DomainStatus::Pending),
            'currency' => strtoupper((string) $currency),
            'first_payment_amount_minor' => $this->nullableMinor(
                isset($this->columns['firstpaymentamount'])
                    ? ($row->{$this->columns['firstpaymentamount']} ?? null)
                    : null,
            ),
            'recurring_amount_minor' => $this->nullableMinor(
                isset($this->columns['recurringamount'])
                    ? ($row->{$this->columns['recurringamount']} ?? null)
                    : null,
            ),
            'interval_unit' => $intervalUnit->value,
            'interval_count' => $intervalCount,
            'registered_at' => $registeredAt->toDateString(),
            'expires_at' => $expiresAt->toDateString(),
            'due_at' => $dueAt->toDateString(),
            'auto_renew' => $this->resolveAutoRenew($row),
            'notes' => $this->nullableString($row->{$this->columns['additionalnotes']} ?? null),
        ];
    }

    private function resolveRegistrarId(string $whmcsRegistrar): int
    {
        $code = Str::slug(strtolower(trim($whmcsRegistrar)), '_');

        if ($code !== '' && isset($this->registrarIdByCode[$code])) {
            return $this->registrarIdByCode[$code];
        }

        if ($this->defaultRegistrarId !== null && Registrar::query()->whereKey($this->defaultRegistrarId)->exists()) {
            return $this->defaultRegistrarId;
        }

        $label = trim($whmcsRegistrar) !== '' ? trim($whmcsRegistrar) : '(empty)';

        throw new \RuntimeException(
            "Unknown WHMCS registrar \"{$label}\" and WHMCS_IMPORT_DEFAULT_REGISTRAR_ID is not set or invalid.",
        );
    }

    /**
     * @return array{0: IntervalUnit, 1: int}
     */
    private function resolveBillingInterval(?int $registrarTldId): array
    {
        if ($registrarTldId === null) {
            return [IntervalUnit::Year, 1];
        }

        $tld = RegistrarTld::query()->find($registrarTldId);

        return Domain::billingIntervalFromRegistrarTld($tld);
    }

    private function resolveAutoRenew(object $row): bool
    {
        if (isset($this->columns['donotrenew'])) {
            return ! (bool) ($row->{$this->columns['donotrenew']} ?? false);
        }

        return false;
    }

    private function normalizeFqdn(string $domain): string
    {
        $fqdn = strtolower(trim($domain));
        $fqdn = preg_replace('#^https?://#', '', $fqdn) ?? $fqdn;
        $fqdn = rtrim($fqdn, '/');
        $fqdn = preg_replace('#/.*$#', '', $fqdn) ?? $fqdn;

        return Str::limit($fqdn, 255, '');
    }

    private function extractTld(string $fqdn): ?string
    {
        $labels = array_values(array_filter(explode('.', $fqdn)));
        $count = count($labels);

        if ($count < 2) {
            return null;
        }

        $secondLevelTlds = ['co', 'com', 'org', 'net', 'ac', 'gov', 'edu', 'me'];

        if ($count >= 3 && in_array($labels[$count - 2], $secondLevelTlds, true)) {
            return $labels[$count - 2].'.'.$labels[$count - 1];
        }

        return $labels[$count - 1];
    }

    private function linkServicesToDomain(Domain $domain): void
    {
        Service::query()
            ->where('client_id', $domain->client_id)
            ->whereNull('domain_id')
            ->whereRaw('LOWER(associated_domain) = ?', [strtolower($domain->fqdn)])
            ->update(['domain_id' => $domain->id]);
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

    private function nullableMinor(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimalToMinor($value);
    }

    private function decimalToMinor(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return (int) round((float) $normalized * 100);
    }

    private function tableExists(string $table): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($table);
    }
}
