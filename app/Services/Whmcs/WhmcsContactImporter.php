<?php

namespace App\Services\Whmcs;

use App\Enums\ContactRole;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhmcsContactImporter
{
    /** @var array<int, int> WHMCS client id → app client id */
    private array $clientIdByWhmcsId = [];

    public function __construct(
        private readonly string $connectionName,
        private readonly string $contactsTable,
        private readonly array $columns,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('whmcs-import');

        return new self(
            connectionName: $config['connection'],
            contactsTable: $config['contacts_table'],
            columns: $config['contact_columns'],
        );
    }

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function whmcsContactCount(): int
    {
        if (! $this->tableExists($this->contactsTable)) {
            return 0;
        }

        return (int) $this->connection()->table($this->contactsTable)->count();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(bool $dryRun = false, ?int $limit = null, ?int $offset = null): array
    {
        $this->loadClientMap();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $query = $this->connection()->table($this->contactsTable)->orderBy($this->columns['id']);

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(200, function ($rows) use ($dryRun, &$stats) {
            foreach ($rows as $row) {
                $whmcsContactId = (int) ($row->{$this->columns['id']} ?? 0);
                $whmcsUserId = (int) ($row->{$this->columns['userid']} ?? 0);

                if ($whmcsContactId <= 0) {
                    $stats['skipped']++;

                    continue;
                }

                if ($whmcsUserId <= 0) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS contact #{$whmcsContactId}: missing userid";

                    continue;
                }

                $clientId = $this->clientIdByWhmcsId[$whmcsUserId] ?? null;
                if ($clientId === null) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS contact #{$whmcsContactId}: no client with whmcs_id {$whmcsUserId} (import clients first)";

                    continue;
                }

                try {
                    $payload = $this->mapRow($row, $clientId);
                } catch (\Throwable $e) {
                    $stats['errors'][] = "WHMCS contact #{$whmcsContactId}: {$e->getMessage()}";
                    $stats['skipped']++;

                    continue;
                }

                if ($payload['email'] === '') {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS contact #{$whmcsContactId}: missing email";

                    continue;
                }

                if ($this->isDuplicateOfClient($clientId, $payload['email'])) {
                    $stats['skipped']++;

                    continue;
                }

                $emailNorm = strtolower($payload['email']);

                if ($dryRun) {
                    $exists = Contact::query()
                        ->where('client_id', $clientId)
                        ->get()
                        ->contains(fn (Contact $c) => strtolower((string) $c->email) === $emailNorm);
                    $exists ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $existing = Contact::query()
                    ->where('client_id', $clientId)
                    ->get()
                    ->first(fn (Contact $c) => strtolower((string) $c->email) === $emailNorm);

                if ($existing !== null) {
                    $existing->fill($payload);
                    $existing->save();
                    $stats['updated']++;
                } else {
                    Contact::query()->create($payload);
                    $stats['created']++;
                }
            }
        }, $this->columns['id']);

        return $stats;
    }

    private function loadClientMap(): void
    {
        $this->clientIdByWhmcsId = Client::query()
            ->whereNotNull('whmcs_id')
            ->pluck('id', 'whmcs_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(object $row, int $clientId): array
    {
        $first = trim((string) ($row->{$this->columns['firstname']} ?? ''));
        $last = trim((string) ($row->{$this->columns['lastname']} ?? ''));
        $company = trim((string) ($row->{$this->columns['companyname']} ?? ''));
        $personName = trim("{$first} {$last}");
        $name = $personName !== '' ? $personName : ($company !== '' ? $company : 'Contact');

        $email = strtolower(trim((string) ($row->{$this->columns['email']} ?? '')));
        $phone = $this->nullableString($row->{$this->columns['phonenumber']} ?? null);

        return [
            'client_id' => $clientId,
            'name' => Str::limit($name, 255, ''),
            'email' => Str::limit($email, 255, ''),
            'phone' => $phone !== null ? Str::limit($phone, 40, '') : null,
            'role' => $this->resolveRole($row),
            'is_primary' => false,
        ];
    }

    private function resolveRole(object $row): ContactRole
    {
        if ($this->flag($row, 'invoiceemails')) {
            return ContactRole::Billing;
        }

        if ($this->flag($row, 'supportemails') || $this->flag($row, 'productemails') || $this->flag($row, 'domainemails')) {
            return ContactRole::Technical;
        }

        if ($this->flag($row, 'subaccount') || $this->flag($row, 'generalemails')) {
            return ContactRole::Owner;
        }

        return ContactRole::Other;
    }

    private function flag(object $row, string $key): bool
    {
        $column = $this->columns[$key] ?? $key;
        $value = $row->{$column} ?? false;

        return (bool) $value;
    }

    private function isDuplicateOfClient(int $clientId, string $email): bool
    {
        $clientEmail = Client::query()->whereKey($clientId)->value('email');

        if ($clientEmail === null || $clientEmail === '') {
            return false;
        }

        return strtolower($clientEmail) === strtolower($email);
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
