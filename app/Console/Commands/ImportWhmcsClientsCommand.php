<?php

namespace App\Console\Commands;

use App\Services\Whmcs\WhmcsClientImporter;
use App\Services\Whmcs\WhmcsContactImporter;
use App\Services\Whmcs\WhmcsSqlLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWhmcsClientsCommand extends Command
{
    protected $signature = 'billing:import-whmcs-clients
                            {--file= : Path to WHMCS .sql dump (use with --prepare to load into WHMCS DB first)}
                            {--prepare : Import --file into the WHMCS database before reading clients}
                            {--connection= : Override WHMCS DB connection name}
                            {--dry-run : Show counts without writing to the database}
                            {--limit= : Max rows to import}
                            {--offset= : Skip first N WHMCS rows}
                            {--with-contacts : Also import tblcontacts after clients}
                            {--only-contacts : Import contacts only (clients must already exist)}';

    protected $description = 'Import clients and/or contacts from WHMCS (tblclients, tblcontacts)';

    public function handle(): int
    {
        if ($connection = $this->option('connection')) {
            config(['whmcs-import.connection' => $connection]);
        }

        $connectionName = config('whmcs-import.connection');

        try {
            DB::connection($connectionName)->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot connect to WHMCS database [{$connectionName}]: {$e->getMessage()}");
            $this->line('Configure WHMCS_DB_* in .env (see .env.example).');

            return self::FAILURE;
        }

        $loader = WhmcsSqlLoader::fromConfig();

        if ($file = $this->option('file')) {
            $path = $this->resolvePath($file);

            if ($this->option('prepare')) {
                $this->info("Loading SQL file into [{$connectionName}]…");
                $loader->loadFile($path);
                $this->info('SQL file loaded.');
            }
        }

        $onlyContacts = (bool) $this->option('only-contacts');
        $withContacts = (bool) $this->option('with-contacts');

        if ($onlyContacts && $withContacts) {
            $this->error('Use either --only-contacts or --with-contacts, not both.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') !== null ? (int) $this->option('offset') : null;
        $dryRun = (bool) $this->option('dry-run');

        if (! $onlyContacts) {
            try {
                $loader->assertClientsTableExists();
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $clientImporter = WhmcsClientImporter::fromConfig();
            $total = $clientImporter->whmcsClientCount();

            $this->info("Found {$total} row(s) in ".config('whmcs-import.clients_table').'.');
            $this->line('Importing clients…');

            $clientStats = $clientImporter->import(
                dryRun: $dryRun,
                limit: $limit,
                offset: $offset,
            );

            $this->reportStats('Clients', $clientStats);
        }

        if ($onlyContacts || $withContacts) {
            try {
                $loader->assertContactsTableExists();
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $contactImporter = WhmcsContactImporter::fromConfig();
            $total = $contactImporter->whmcsContactCount();

            $this->newLine();
            $this->info("Found {$total} row(s) in ".config('whmcs-import.contacts_table').'.');
            $this->line('Importing contacts…');

            $contactStats = $contactImporter->import(
                dryRun: $dryRun,
                limit: $limit,
                offset: $offset,
            );

            $this->reportStats('Contacts', $contactStats);
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }

    /**
     * @param  array{created: int, updated: int, skipped: int, errors: list<string>}  $stats
     */
    private function reportStats(string $label, array $stats): void
    {
        $this->newLine();
        $this->info($label);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
            ],
        );

        if ($stats['errors'] !== []) {
            $this->warn("{$label} issues (first 20):");
            foreach (array_slice($stats['errors'], 0, 20) as $message) {
                $this->line("  • {$message}");
            }
            if (count($stats['errors']) > 20) {
                $this->line('  … '.(count($stats['errors']) - 20).' more');
            }
        }
    }

    private function resolvePath(string $file): string
    {
        if ($file[0] === '/' || (strlen($file) > 2 && $file[1] === ':')) {
            return $file;
        }

        return base_path($file);
    }
}
