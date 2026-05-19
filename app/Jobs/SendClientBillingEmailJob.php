<?php

namespace App\Jobs;

use App\Mail\BillingTemplateMail;
use App\Models\ClientEmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SendClientBillingEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 120, 300, 600];

    public int $timeout = 120;

    /**
     * @param  list<array{name: string, path: string}>  $queuedAttachments  Paths relative to the local disk.
     */
    public function __construct(
        public int $clientId,
        public int $emailTemplateId,
        public ?string $emailTemplateSlug,
        public ?int $orderId,
        public ?int $serviceId,
        public string $to,
        public ?string $cc,
        public ?string $bcc,
        public string $subject,
        public string $message,
        public array $queuedAttachments = [],
    ) {
        $this->onQueue(config('billing.mail.queue', 'default'));
    }

    public function handle(): void
    {
        $fileAttachments = $this->resolveFileAttachments();

        $pending = Mail::to($this->to);
        if ($this->cc !== null && $this->cc !== '') {
            $pending->cc($this->parseAddresses($this->cc));
        }
        if ($this->bcc !== null && $this->bcc !== '') {
            $pending->bcc($this->parseAddresses($this->bcc));
        }

        $pending->send(new BillingTemplateMail(
            $this->subject,
            $this->message,
            $fileAttachments,
        ));

        $this->recordEmailLog();
    }

    public function failed(?Throwable $exception): void
    {
        foreach ($this->queuedAttachments as $file) {
            $path = $file['path'] ?? '';
            if ($path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    /**
     * @return list<array{path: string, name: string}>
     */
    private function resolveFileAttachments(): array
    {
        $resolved = [];

        foreach ($this->queuedAttachments as $file) {
            $relativePath = $file['path'] ?? '';
            if ($relativePath === '') {
                continue;
            }

            $fullPath = Storage::disk('local')->path($relativePath);
            if (! is_readable($fullPath)) {
                continue;
            }

            $resolved[] = [
                'path' => $fullPath,
                'name' => $file['name'] ?? basename($relativePath),
            ];
        }

        return $resolved;
    }

    private function recordEmailLog(): void
    {
        $log = ClientEmailLog::query()->create([
            'client_id' => $this->clientId,
            'email_template_id' => $this->emailTemplateId,
            'email_template_slug' => $this->emailTemplateSlug,
            'order_id' => $this->orderId,
            'service_id' => $this->serviceId,
            'subject' => $this->subject,
            'message' => $this->message,
            'to_address' => $this->to,
            'cc' => $this->cc !== null && $this->cc !== '' ? $this->cc : null,
            'bcc' => $this->bcc !== null && $this->bcc !== '' ? $this->bcc : null,
            'attachments' => null,
            'sent_at' => now(),
        ]);

        if ($this->queuedAttachments === []) {
            return;
        }

        $stored = [];

        foreach ($this->queuedAttachments as $file) {
            $sourcePath = $file['path'] ?? '';
            if ($sourcePath === '' || ! Storage::disk('local')->exists($sourcePath)) {
                continue;
            }

            $safeName = Str::slug(pathinfo($file['name'] ?? 'attachment', PATHINFO_FILENAME)) ?: 'attachment';
            $extension = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
            $filename = $extension !== ''
                ? "{$safeName}.{$extension}"
                : $safeName;

            $destPath = "client-email-logs/{$this->clientId}/{$log->id}/{$filename}";
            Storage::disk('local')->put($destPath, Storage::disk('local')->get($sourcePath));
            Storage::disk('local')->delete($sourcePath);

            $stored[] = [
                'name' => $file['name'] ?? $filename,
                'path' => $destPath,
            ];
        }

        if ($stored !== []) {
            $log->update(['attachments' => $stored]);
        }
    }

    /**
     * @return list<string>
     */
    private function parseAddresses(string $addresses): array
    {
        return collect(explode(',', $addresses))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->values()
            ->all();
    }
}
