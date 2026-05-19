<?php

namespace App\Models;

use App\Enums\ServerStatus;
use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Server extends Model
{
    protected $fillable = [
        'name', 'hostname', 'panel_url', 'status', 'cost_amount_minor', 'cost_currency', 'specs', 'notes',
    ];

    protected function casts(): array
    {
        return ['status' => ServerStatus::class, 'specs' => 'array'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function allocatingServices(): HasMany
    {
        return $this->hasMany(Service::class)->whereNot('status', ServiceStatus::Cancelled);
    }

    public function capacityRamMb(): ?int
    {
        $specs = $this->specs ?? [];
        if (isset($specs['total_ram_mb']) && is_numeric($specs['total_ram_mb'])) {
            return max(0, (int) $specs['total_ram_mb']);
        }
        if (isset($specs['ram_gb']) && is_numeric($specs['ram_gb'])) {
            return max(0, (int) round((float) $specs['ram_gb'] * 1024));
        }

        return null;
    }

    public function capacityDiskGb(): ?int
    {
        $specs = $this->specs ?? [];
        if (isset($specs['total_disk_gb']) && is_numeric($specs['total_disk_gb'])) {
            return max(0, (int) $specs['total_disk_gb']);
        }
        if (isset($specs['disk_gb']) && is_numeric($specs['disk_gb'])) {
            return max(0, (int) round((float) $specs['disk_gb']));
        }

        return null;
    }

    public function usedAllocatedRamMb(): int
    {
        return (int) $this->services()
            ->whereNot('status', ServiceStatus::Cancelled)
            ->sum('allocated_ram_mb');
    }

    public function usedAllocatedDiskGb(): int
    {
        return (int) $this->services()
            ->whereNot('status', ServiceStatus::Cancelled)
            ->sum('allocated_disk_gb');
    }

    /** RAM allocated to services in **active** status (excludes pending/suspended/cancelled). */
    public function allocatedRamMbActiveServices(): int
    {
        return (int) $this->services()
            ->where('status', ServiceStatus::Active)
            ->sum('allocated_ram_mb');
    }

    /** Disk allocated to services in **active** status. */
    public function allocatedDiskGbActiveServices(): int
    {
        return (int) $this->services()
            ->where('status', ServiceStatus::Active)
            ->sum('allocated_disk_gb');
    }

    public function ramUsagePercentActive(): ?float
    {
        $cap = $this->capacityRamMb();
        if ($cap === null || $cap <= 0) {
            return null;
        }

        return round(min(100, 100 * $this->allocatedRamMbActiveServices() / $cap), 1);
    }

    public function diskUsagePercentActive(): ?float
    {
        $cap = $this->capacityDiskGb();
        if ($cap === null || $cap <= 0) {
            return null;
        }

        return round(min(100, 100 * $this->allocatedDiskGbActiveServices() / $cap), 1);
    }

    /**
     * One query: active-service allocation sums per server id (for list pages).
     *
     * @param  iterable<int>  $serverIds
     * @return Collection<int, array{ram_mb: int, disk_gb: int}>
     */
    public static function activeAllocationByServerIds(iterable $serverIds): Collection
    {
        $ids = collect($serverIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return collect();
        }

        return Service::query()
            ->whereIn('server_id', $ids)
            ->where('status', ServiceStatus::Active)
            ->groupBy('server_id')
            ->selectRaw('server_id, sum(coalesce(allocated_ram_mb, 0)) as ram_mb, sum(coalesce(allocated_disk_gb, 0)) as disk_gb')
            ->get()
            ->keyBy('server_id')
            ->map(fn ($row) => ['ram_mb' => (int) $row->ram_mb, 'disk_gb' => (int) $row->disk_gb]);
    }

    public function freeRamMb(): ?int
    {
        $cap = $this->capacityRamMb();
        if ($cap === null) {
            return null;
        }

        return max(0, $cap - $this->usedAllocatedRamMb());
    }

    public function freeDiskGb(): ?int
    {
        $cap = $this->capacityDiskGb();
        if ($cap === null) {
            return null;
        }

        return max(0, $cap - $this->usedAllocatedDiskGb());
    }

    public static function formatRamMb(int|string|null $megabytes): string
    {
        if ($megabytes === null || $megabytes === '') {
            return '—';
        }
        $megabytes = (int) $megabytes;
        if ($megabytes >= 1024) {
            $gib = $megabytes / 1024;
            $rounded = abs($gib - round($gib)) < 0.05 ? (string) (int) round($gib) : number_format($gib, 1);

            return $rounded.' '.__('GiB');
        }

        return number_format($megabytes).' '.__('MiB');
    }

    public static function formatDiskGb(int|string|null $gigabytes): string
    {
        if ($gigabytes === null || $gigabytes === '') {
            return '—';
        }

        return number_format((int) $gigabytes).' '.__('GB');
    }
}
