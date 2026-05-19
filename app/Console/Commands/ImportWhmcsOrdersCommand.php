<?php

namespace App\Console\Commands;

use App\Services\Whmcs\WhmcsOrderImporter;
use App\Services\Whmcs\WhmcsSqlLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWhmcsOrdersCommand extends Command
{
    protected $signature = 'billing:import-whmcs-orders
                            {--file= : Path to WHMCS .sql dump (use with --prepare to load into WHMCS DB first)}
                            {--prepare : Import --file into the WHMCS database before reading orders}
                            {--connection= : Override WHMCS DB connection name}
                            {--dry-run : Show counts without writing to the database}
                            {--all : Import all invoice statuses (default: paid only)}
                            {--statuses= : Comma-separated WHMCS statuses (e.g. Paid,Unpaid)}
                            {--limit= : Max rows to import}
                            {--offset= : Skip first N WHMCS rows}';

    protected $description = 'Import orders from WHMCS tblinvoices and tblinvoiceitems (import clients first)';

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

        try {
            $loader->assertInvoicesTableExists();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') !== null ? (int) $this->option('offset') : null;

        $statusFilter = $this->resolveStatusFilter();
        if ($statusFilter === false) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        if ($statusFilter === null) {
            $this->info('Importing all invoice statuses.');
        } else {
            $this->info('Importing orders with status: '.implode(', ', $statusFilter));
        }

        $importer = WhmcsOrderImporter::fromConfig();
        $total = $importer->whmcsOrderCount($statusFilter);

        $this->info("Found {$total} row(s) in ".config('whmcs-import.invoices_table').'.');

        if (DB::connection($connectionName)->getSchemaBuilder()->hasTable(config('whmcs-import.invoice_items_table'))) {
            $this->line('Line items will be imported from '.config('whmcs-import.invoice_items_table').'.');
        } else {
            $this->warn('Line items table not found — importing order headers only.');
        }

        $this->line('Importing orders…');

        $stats = $importer->import(
            dryRun: $dryRun,
            limit: $limit,
            offset: $offset,
            statusFilter: $statusFilter,
        );

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
            ],
        );

        if ($stats['errors'] !== []) {
            $this->warn('Issues (first 20):');
            foreach (array_slice($stats['errors'], 0, 20) as $message) {
                $this->line("  • {$message}");
            }
            if (count($stats['errors']) > 20) {
                $this->line('  … '.(count($stats['errors']) - 20).' more');
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null|false null = all statuses, list = filter, false = validation error
     */
    private function resolveStatusFilter(): array|null|false
    {
        if ($this->option('all') && $this->option('statuses')) {
            $this->error('Use either --all or --statuses, not both.');

            return false;
        }

        if ($statuses = $this->option('statuses')) {
            $parsed = array_values(array_filter(array_map('trim', explode(',', (string) $statuses))));

            if ($parsed === []) {
                $this->error('--statuses must list at least one status (e.g. Paid,Unpaid).');

                return false;
            }

            return $parsed;
        }

        if ($this->option('all')) {
            return null;
        }

        return config('whmcs-import.invoice_paid_statuses');
    }

    private function resolvePath(string $file): string
    {
        if ($file[0] === '/' || (strlen($file) > 2 && $file[1] === ':')) {
            return $file;
        }

        return base_path($file);
    }
}
