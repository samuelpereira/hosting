<?php

namespace App\Models;

use App\Enums\OrderItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'whmcs_id', 'order_id', 'position', 'type', 'description', 'quantity', 'unit_amount_minor', 'tax_amount_minor', 'service_id', 'domain_id',
    ];

    protected function casts(): array
    {
        return ['type' => OrderItemType::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
