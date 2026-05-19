<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{path: string, name: string}>  $attachments
     */
    public function __construct(
        public string $mailSubject,
        public string $htmlBody,
        public array $fileAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return collect($this->fileAttachments)
            ->map(fn (array $file) => Attachment::fromPath($file['path'])->as($file['name']))
            ->all();
    }
}
