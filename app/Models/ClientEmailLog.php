<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientEmailLog extends Model
{
    protected $fillable = [
        'client_id',
        'email_template_id',
        'email_template_slug',
        'order_id',
        'service_id',
        'subject',
        'message',
        'to_address',
        'cc',
        'bcc',
        'attachments',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    public function attachmentFiles(): array
    {
        $files = $this->attachments ?? [];

        return is_array($files) ? $files : [];
    }

    public function hasAttachments(): bool
    {
        return $this->attachmentFiles() !== [];
    }
}
