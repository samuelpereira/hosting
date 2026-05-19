<?php

namespace App\Console\Commands;

use App\Services\Whmcs\WhmcsHostingImporter;
use App\Services\Whmcs\WhmcsProductImporter;
use App\Services\Whmcs\WhmcsSqlLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWhmcsHostingCommand extends Command
{
    protected $signature = 'billing:import-whmcs-hosting
                            {--file= : Path to WHMCS .sql dump (use with --prepare to load into WHMCS DB first)}
                            {--prepare : Import --file into the WHMCS database before reading hosting}
                            {--connection= : Override WHMCS DB connection name}
                            {--sync-products : Link tblproducts to catalog via config/whmcs-product-map.php before hosting import}
                            {--dry-run : Show counts without writing to the database}
                            {--limit= : Max rows to import}
                            {--offset= : Skip first N WHMCS rows}';

    protected $description = 'Import services from WHMCS tblhosting (import clients and products first)';

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

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') !== null ? (int) $this->option('offset') : null;

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        if ($this->option('sync-products')) {
            try {
                $loader->assertProductsTableExists();
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $productImporter = WhmcsProductImporter::fromConfig();
            $total = $productImporter->whmcsProductCount();

            $this->info("Found {$total} row(s) in ".config('whmcs-import.products_table').'.');
            $map = config('whmcs-import.product_name_map', []);
            $mapCount = count($map['names'] ?? $map);
            $this->line("Linking products ({$mapCount} name map entries, no new products created)…");

            $this->reportProductStats($productImporter->import(
                dryRun: $dryRun,
                limit: $limit,
                offset: $offset,
            ));
        } elseif (config('whmcs-import.default_product_id') === null) {
            $this->warn('Tip: add entries to config/whmcs-product-map.php and run with --sync-products, or set WHMCS_IMPORT_DEFAULT_PRODUCT_ID.');
        }

        try {
            $loader->assertHostingTableExists();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $hostingImporter = WhmcsHostingImporter::fromConfig();
        $total = $hostingImporter->whmcsHostingCount();

        $this->newLine();
        $this->info("Found {$total} row(s) in ".config('whmcs-import.hosting_table').'.');
        $this->line('Importing services…');

        $this->reportStats('Services', $hostingImporter->import(
            dryRun: $dryRun,
            limit: $limit,
            offset: $offset,
        ));

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }

    /**
     * @param  array{linked: int, mapped: int, updated: int, skipped: int, errors: list<string>}  $stats
     */
    private function reportProductStats(array $stats): void
    {
        $this->newLine();
        $this->info('Products');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Linked (whmcs_id set)', $stats['linked']],
                ['Mapped (shared slug)', $stats['mapped'] ?? 0],
                ['Already linked', $stats['updated']],
                ['Skipped', $stats['skipped']],
            ],
        );

        if ($stats['errors'] !== []) {
            $this->warn('Product issues (first 20):');
            foreach (array_slice($stats['errors'], 0, 20) as $message) {
                $this->line("  • {$message}");
            }
            if (count($stats['errors']) > 20) {
                $this->line('  … '.(count($stats['errors']) - 20).' more');
            }
        }
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
