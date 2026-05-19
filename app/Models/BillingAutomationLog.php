<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BillingAutomationLog extends Model
{
    public const SUBJECT_SERVICE = 'service';

    public const SUBJECT_DOMAIN = 'domain';

    public const SUBJECT_ORDER = 'order';

    public const EVENT_SERVICE_RENEWAL_ORDER = 'service_renewal_order';

    public const EVENT_DOMAIN_RENEWAL_ORDER = 'domain_renewal_order';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'event',
        'context_date',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'context_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function wasHandled(
        string $subjectType,
        int $subjectId,
        string $event,
        CarbonInterface $contextDate,
    ): bool {
        return static::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('event', $event)
            ->whereDate('context_date', Carbon::instance($contextDate)->toDateString())
            ->exists();
    }

    public static function record(
        string $subjectType,
        int $subjectId,
        string $event,
        CarbonInterface $contextDate,
        ?int $orderId = null,
    ): self {
        return static::query()->firstOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'event' => $event,
                'context_date' => Carbon::instance($contextDate)->toDateString(),
            ],
            [
                'order_id' => $orderId,
            ],
        );
    }
}
