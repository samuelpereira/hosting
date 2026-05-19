<?php

namespace App\Console\Commands;

use App\Services\Whmcs\WhmcsDomainImporter;
use App\Services\Whmcs\WhmcsSqlLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWhmcsDomainsCommand extends Command
{
    protected $signature = 'billing:import-whmcs-domains
                            {--file= : Path to WHMCS .sql dump (use with --prepare to load into WHMCS DB first)}
                            {--prepare : Import --file into the WHMCS database before reading domains}
                            {--connection= : Override WHMCS DB connection name}
                            {--dry-run : Show counts without writing to the database}
                            {--active-only : Import only domains with Active status in WHMCS}
                            {--limit= : Max rows to import}
                            {--offset= : Skip first N WHMCS rows}';

    protected $description = 'Import domains from WHMCS tbldomains (import clients first)';

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
            $loader->assertDomainsTableExists();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $activeOnly = (bool) $this->option('active-only');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') !== null ? (int) $this->option('offset') : null;

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        if ($activeOnly) {
            $this->info('Filtering to active domains only ('.implode(', ', config('whmcs-import.domain_active_statuses')).').');
        }

        $importer = WhmcsDomainImporter::fromConfig();
        $total = $importer->whmcsDomainCount($activeOnly);

        $this->info("Found {$total} row(s) in ".config('whmcs-import.domains_table').'.');
        $this->line('Importing domains…');

        $stats = $importer->import(
            dryRun: $dryRun,
            limit: $limit,
            offset: $offset,
            activeOnly: $activeOnly,
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

    private function resolvePath(string $file): string
    {
        if ($file[0] === '/' || (strlen($file) > 2 && $file[1] === ':')) {
            return $file;
        }

        return base_path($file);
    }
}
