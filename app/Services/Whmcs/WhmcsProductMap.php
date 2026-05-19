<?php

namespace App\Services\Whmcs;

/**
 * Resolves WHMCS tblproducts rows to app product slugs.
 *
 * @phpstan-type MapConfig array{
 *     names?: array<string, string>,
 *     ids?: array<int|string, string>,
 * }|array<string, string>
 */
class WhmcsProductMap
{
    /** @var array<string, string> */
    private array $byName;

    /** @var array<int, string> */
    private array $byId;

    /**
     * @param  MapConfig  $config
     */
    public function __construct(array $config)
    {
        if (isset($config['names']) || isset($config['ids'])) {
            $this->byName = $config['names'] ?? [];
            $this->byId = $this->normalizeIdMap($config['ids'] ?? []);
        } else {
            $this->byName = $config;
            $this->byId = [];
        }
    }

    public static function fromConfig(): self
    {
        return new self(config('whmcs-import.product_name_map', []));
    }

    public function slugForWhmcsId(int $whmcsProductId): ?string
    {
        return $this->byId[$whmcsProductId] ?? null;
    }

    public function slugForName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        if (isset($this->byName[$name])) {
            return $this->normalizeSlug($this->byName[$name]);
        }

        foreach ($this->byName as $mapName => $slug) {
            if (strcasecmp(trim((string) $mapName), $name) === 0) {
                return $this->normalizeSlug($slug);
            }
        }

        return null;
    }

    public function slugForPackage(int $whmcsProductId, string $name): ?string
    {
        return $this->slugForWhmcsId($whmcsProductId) ?? $this->slugForName($name);
    }

    public function isEmpty(): bool
    {
        return $this->byName === [] && $this->byId === [];
    }

    /**
     * @param  array<int|string, string>  $map
     * @return array<int, string>
     */
    private function normalizeIdMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $id => $slug) {
            $normalized[(int) $id] = $this->normalizeSlug((string) $slug);
        }

        return $normalized;
    }

    private function normalizeSlug(string $slug): string
    {
        return trim($slug);
    }
}
