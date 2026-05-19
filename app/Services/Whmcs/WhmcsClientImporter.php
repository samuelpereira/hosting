<?php

namespace App\Services\Whmcs;

use App\Models\Client;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhmcsClientImporter
{
    /** @var array<int, string> */
    private array $currencyById = [];

    public function __construct(
        private readonly string $connectionName,
        private readonly string $clientsTable,
        private readonly string $currenciesTable,
        private readonly array $columns,
        private readonly string $defaultCurrency,
        private readonly array $activeStatuses,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('whmcs-import');

        return new self(
            connectionName: $config['connection'],
            clientsTable: $config['clients_table'],
            currenciesTable: $config['currencies_table'],
            columns: $config['columns'],
            defaultCurrency: $config['default_currency'],
            activeStatuses: $config['active_statuses'],
        );
    }

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function whmcsClientCount(): int
    {
        return (int) $this->connection()->table($this->clientsTable)->count();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(bool $dryRun = false, ?int $limit = null, ?int $offset = null): array
    {
        $this->loadCurrencyMap();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $query = $this->connection()->table($this->clientsTable)->orderBy($this->columns['id']);

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(200, function ($rows) use ($dryRun, &$stats) {
            foreach ($rows as $row) {
                $whmcsId = (int) ($row->{$this->columns['id']} ?? 0);
                if ($whmcsId <= 0) {
                    $stats['skipped']++;

                    continue;
                }

                try {
                    $payload = $this->mapRow($row);
                } catch (\Throwable $e) {
                    $stats['errors'][] = "WHMCS #{$whmcsId}: {$e->getMessage()}";
                    $stats['skipped']++;

                    continue;
                }

                if ($payload['name'] === '' && ($payload['email'] === null || $payload['email'] === '')) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS #{$whmcsId}: missing name and email";

                    continue;
                }

                if ($dryRun) {
                    $exists = Client::query()->where('whmcs_id', $whmcsId)->exists();
                    $exists ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $client = Client::query()->updateOrCreate(
                    ['whmcs_id' => $whmcsId],
                    $payload,
                );

                if ($client->wasRecentlyCreated) {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }
            }
        }, $this->columns['id']);

        return $stats;
    }

    private function loadCurrencyMap(): void
    {
        $this->currencyById = [];

        if (! $this->tableExists($this->currenciesTable)) {
            return;
        }

        $codeColumn = $this->connection()->getSchemaBuilder()->hasColumn($this->currenciesTable, 'code')
            ? 'code'
            : null;

        if ($codeColumn === null) {
            return;
        }

        $this->currencyById = $this->connection()
            ->table($this->currenciesTable)
            ->pluck($codeColumn, 'id')
            ->map(fn ($code) => strtoupper((string) $code))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(object $row): array
    {
        $first = trim((string) ($row->{$this->columns['firstname']} ?? ''));
        $last = trim((string) ($row->{$this->columns['lastname']} ?? ''));
        $company = trim((string) ($row->{$this->columns['companyname']} ?? ''));
        $personName = trim("{$first} {$last}");
        $name = $personName !== '' ? $personName : ($company !== '' ? $company : 'Client');

        $address1 = trim((string) ($row->{$this->columns['address1']} ?? ''));
        $address2 = trim((string) ($row->{$this->columns['address2']} ?? ''));
        $addressLine = $address2 !== '' ? "{$address1}, {$address2}" : $address1;

        $country = $this->normalizeCountry($row->{$this->columns['country']} ?? null);
        $email = $this->nullableString($row->{$this->columns['email']} ?? null);
        if ($email !== null) {
            $email = strtolower($email);
        }
        $status = (string) ($row->{$this->columns['status']} ?? '');
        $postalCode = $this->nullableString($row->{$this->columns['postcode']} ?? null);
        $city = $this->nullableString($row->{$this->columns['city']} ?? null);
        $phone = $this->nullableString($row->{$this->columns['phonenumber']} ?? null);
        $vatNumber = $this->nullableString($row->{$this->columns['tax_id']} ?? null);

        return [
            'whmcs_id' => (int) $row->{$this->columns['id']},
            'name' => Str::limit($name, 255, ''),
            'company_name' => $company !== '' ? Str::limit($company, 255, '') : null,
            'email' => $email !== null ? Str::limit($email, 255, '') : null,
            'address_line' => $addressLine !== '' ? $addressLine : null,
            'postal_code' => $postalCode !== null ? Str::limit($postalCode, 32, '') : null,
            'city' => $city !== null ? Str::limit($city, 120, '') : null,
            'country' => $country,
            'phone' => $phone !== null ? Str::limit($phone, 40, '') : null,
            'vat_number' => $vatNumber !== null ? Str::limit($vatNumber, 32, '') : null,
            'vat_exception' => $taxExempt = (bool) ($row->{$this->columns['taxexempt']} ?? false),
            'irs_exception' => $taxExempt,
            'currency' => $this->resolveCurrency($row->{$this->columns['currency']} ?? null),
            'is_active' => in_array($status, $this->activeStatuses, true),
            'registered_at' => $this->parseDate(
                isset($this->columns['datecreated']) ? ($row->{$this->columns['datecreated']} ?? null) : null,
            ),
            'notes' => $this->nullableString($row->{$this->columns['notes']} ?? null),
        ];
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

    private function resolveCurrency(mixed $currencyField): string
    {
        if ($currencyField === null || $currencyField === '') {
            return strtoupper($this->defaultCurrency);
        }

        if (is_numeric($currencyField)) {
            $id = (int) $currencyField;
            if (isset($this->currencyById[$id])) {
                return $this->currencyById[$id];
            }

            return strtoupper($this->defaultCurrency);
        }

        $code = strtoupper(trim((string) $currencyField));

        return strlen($code) === 3 ? $code : strtoupper($this->defaultCurrency);
    }

    private function normalizeCountry(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $country = strtoupper(trim((string) $value));

        return strlen($country) >= 2 ? substr($country, 0, 2) : null;
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
