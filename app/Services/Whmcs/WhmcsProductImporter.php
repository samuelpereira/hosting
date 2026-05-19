<?php

namespace App\Services\Whmcs;

use App\Models\Product;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class WhmcsProductImporter
{
    public function __construct(
        private readonly string $connectionName,
        private readonly string $productsTable,
        private readonly array $columns,
        private readonly WhmcsProductMap $productMap,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('whmcs-import');

        return new self(
            connectionName: $config['connection'],
            productsTable: $config['products_table'],
            columns: $config['product_columns'],
            productMap: WhmcsProductMap::fromConfig(),
        );
    }

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function whmcsProductCount(): int
    {
        if (! $this->tableExists($this->productsTable)) {
            return 0;
        }

        return (int) $this->connection()->table($this->productsTable)->count();
    }

    /**
     * @return array{linked: int, mapped: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(bool $dryRun = false, ?int $limit = null, ?int $offset = null): array
    {
        $stats = ['linked' => 0, 'mapped' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $query = $this->connection()->table($this->productsTable)->orderBy($this->columns['id']);

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(100, function ($rows) use ($dryRun, &$stats) {
            foreach ($rows as $row) {
                $whmcsId = (int) ($row->{$this->columns['id']} ?? 0);
                if ($whmcsId <= 0) {
                    $stats['skipped']++;

                    continue;
                }

                $name = trim((string) ($row->{$this->columns['name']} ?? ''));
                if ($name === '') {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS product #{$whmcsId}: missing name";

                    continue;
                }

                $slug = $this->productMap->slugForPackage($whmcsId, $name);
                if ($slug === null) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS product #{$whmcsId} ({$name}): not in product_name_map";

                    continue;
                }

                $product = Product::query()->where('slug', $slug)->first();
                if ($product === null) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS product #{$whmcsId} ({$name}): no app product with slug \"{$slug}\"";

                    continue;
                }

                if ($product->whmcs_id !== null && (int) $product->whmcs_id !== $whmcsId) {
                    $stats['mapped']++;

                    continue;
                }

                $conflict = Product::query()
                    ->where('whmcs_id', $whmcsId)
                    ->whereKeyNot($product->id)
                    ->exists();
                if ($conflict) {
                    $stats['skipped']++;
                    $stats['errors'][] = "WHMCS product #{$whmcsId} ({$name}): whmcs_id already used by another product";

                    continue;
                }

                if ($dryRun) {
                    $product->whmcs_id === null ? $stats['linked']++ : $stats['updated']++;

                    continue;
                }

                if ($product->whmcs_id === null) {
                    $product->whmcs_id = $whmcsId;
                    $product->save();
                    $stats['linked']++;
                } else {
                    $stats['updated']++;
                }
            }
        }, $this->columns['id']);

        return $stats;
    }

    private function tableExists(string $table): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($table);
    }
}
