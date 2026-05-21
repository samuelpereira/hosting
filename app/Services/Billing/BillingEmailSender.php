<?php

namespace App\Services\Billing;

use App\Jobs\SendClientBillingEmailJob;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BillingEmailSender
{
    /** @var list<string> */
    public const ORDER_TEMPLATE_SLUGS = [
        'order_created',
        'order_paid',
        'order_payment_reminder',
    ];

    /** @var list<string> */
    public const SERVICE_TEMPLATE_SLUGS = [
        'service_welcome',
    ];

    public function sendToClient(
        EmailTemplate $template,
        string $to,
        array $variables,
        int $clientId,
        ?Transaction $transaction = null,
        ?int $orderId = null,
        ?string $cc = null,
        ?string $bcc = null,
    ): void {
        if (! in_array($template->slug, self::ORDER_TEMPLATE_SLUGS, true)) {
            throw new InvalidArgumentException(__('This template cannot be sent for an order.'));
        }

        $this->queue(
            $template,
            $to,
            $this->variablesForOrderSend($template, $variables, $transaction),
            $clientId,
            $transaction,
            $orderId,
            null,
            $cc,
            $bcc,
        );
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public function sendServiceEmail(
        EmailTemplate $template,
        string $to,
        array $variables,
        int $clientId,
        ?int $serviceId = null,
        ?string $cc = null,
        ?string $bcc = null,
    ): void {
        if (! in_array($template->slug, self::SERVICE_TEMPLATE_SLUGS, true)) {
            throw new InvalidArgumentException(__('This template cannot be sent for a service.'));
        }

        $this->queue($template, $to, $variables, $clientId, null, null, $serviceId, $cc, $bcc);
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    private function queue(
        EmailTemplate $template,
        string $to,
        array $variables,
        int $clientId,
        ?Transaction $transaction,
        ?int $orderId,
        ?int $serviceId,
        ?string $cc,
        ?string $bcc,
    ): void {
        if (! $template->is_active) {
            throw new InvalidArgumentException(__('This email template is inactive.'));
        }

        $delivery = $this->resolveDeliveryAddresses($clientId, $to, $cc);

        $rendered = $template->render($variables);

        $attachments = [];
        if ($transaction?->hasDocument()) {
            $attachments[] = [
                'path' => Storage::disk('local')->path($transaction->document_path),
                'name' => $transaction->document_name ?? basename($transaction->document_path),
            ];
        }

        SendClientBillingEmailJob::dispatch(
            clientId: $clientId,
            emailTemplateId: $template->id,
            emailTemplateSlug: $template->slug,
            orderId: $orderId,
            serviceId: $serviceId,
            to: $delivery['to'],
            cc: $delivery['cc'],
            bcc: $bcc,
            subject: $rendered['subject'],
            message: $rendered['body'],
            queuedAttachments: $this->prepareAttachmentsForQueue($attachments),
        );
    }

    /**
     * @return array{to: string, cc: string|null}
     */
    private function resolveDeliveryAddresses(int $clientId, string $primaryTo, ?string $cc): array
    {
        $client = Client::query()->with('contacts')->find($clientId);
        $recipients = $client?->billingEmailRecipients() ?? [];

        if ($recipients === []) {
            $normalized = strtolower(trim($primaryTo));
            if ($normalized === '') {
                throw new InvalidArgumentException(__('No email addresses for this client.'));
            }

            $recipients = [$normalized];
        }

        $contactCc = array_slice($recipients, 1);
        $mergedCc = $this->mergeAddressLists($contactCc, $this->parseAddressList($cc));

        return [
            'to' => $recipients[0],
            'cc' => $mergedCc !== [] ? implode(', ', $mergedCc) : null,
        ];
    }

    /**
     * @param  list<string>  $lists
     * @return list<string>
     */
    private function mergeAddressLists(...$lists): array
    {
        $merged = [];

        foreach ($lists as $list) {
            foreach ($list as $email) {
                $normalized = strtolower(trim($email));
                if ($normalized !== '' && ! in_array($normalized, $merged, true)) {
                    $merged[] = $normalized;
                }
            }
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    private function parseAddressList(?string $addresses): array
    {
        if ($addresses === null || trim($addresses) === '') {
            return [];
        }

        return $this->mergeAddressLists(
            collect(explode(',', $addresses))
                ->map(fn (string $email) => trim($email))
                ->filter()
                ->all(),
        );
    }

    /**
     * Copy attachments to queue storage so they remain available until the job runs.
     *
     * @param  list<array{path: string, name: string}>  $attachments
     * @return list<array{name: string, path: string}>
     */
    private function prepareAttachmentsForQueue(array $attachments): array
    {
        $prepared = [];

        foreach ($attachments as $file) {
            if (! is_readable($file['path'])) {
                continue;
            }

            $safeName = Str::slug(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'attachment';
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $extension !== ''
                ? "{$safeName}.{$extension}"
                : $safeName;

            $path = 'client-email-logs/queue/'.Str::uuid()."/{$filename}";
            Storage::disk('local')->put($path, file_get_contents($file['path']));

            $prepared[] = [
                'name' => $file['name'],
                'path' => $path,
            ];
        }

        return $prepared;
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     * @return array<string, string|int|float|null>
     */
    private function variablesForOrderSend(EmailTemplate $template, array $variables, ?Transaction $transaction): array
    {
        if ($template->slug === 'order_paid') {
            $variables['attachment_notice'] = $transaction?->hasDocument()
                ? '<p>Segue em anexo o recibo do pagamento efetuado.</p>'
                : '';
        }

        return $variables;
    }
}
