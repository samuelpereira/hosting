<?php

use App\Enums\ContactRole;
use App\Jobs\SendClientBillingEmailJob;
use App\Models\Client;
use App\Models\ClientEmailLog;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Services\Billing\BillingEmailSender;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Queue;

test('billing emails are dispatched to the queue', function () {
    Queue::fake();
    $this->seed(EmailTemplateSeeder::class);

    $client = Client::query()->create([
        'name' => 'Queue Client',
        'email' => 'queue@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $template = EmailTemplate::query()->where('slug', 'order_created')->firstOrFail();

    app(BillingEmailSender::class)->sendToClient(
        $template,
        $client->email,
        ['client_name' => $client->name, 'order_number' => 'ORD-Q-1'],
        $client->id,
    );

    Queue::assertPushed(SendClientBillingEmailJob::class, function (SendClientBillingEmailJob $job) use ($client) {
        return $job->clientId === $client->id
            && $job->to === 'queue@test.example'
            && $job->emailTemplateSlug === 'order_created';
    });

    expect(ClientEmailLog::query()->count())->toBe(0);
});

test('billing email includes client contacts in cc', function () {
    Queue::fake();
    $this->seed(EmailTemplateSeeder::class);

    $client = Client::query()->create([
        'name' => 'Queue Client',
        'email' => 'client@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    Contact::query()->create([
        'client_id' => $client->id,
        'name' => 'Billing Contact',
        'email' => 'billing-contact@test.example',
        'role' => ContactRole::Billing,
        'is_primary' => false,
    ]);

    $template = EmailTemplate::query()->where('slug', 'order_created')->firstOrFail();

    app(BillingEmailSender::class)->sendToClient(
        $template,
        $client->email,
        ['client_name' => $client->name, 'order_number' => 'ORD-Q-2'],
        $client->id,
    );

    Queue::assertPushed(SendClientBillingEmailJob::class, function (SendClientBillingEmailJob $job) {
        return $job->to === 'client@test.example'
            && str_contains((string) $job->cc, 'billing-contact@test.example');
    });
});

test('queued billing email job retries on failure', function () {
    $job = new SendClientBillingEmailJob(
        clientId: 1,
        emailTemplateId: 1,
        emailTemplateSlug: 'order_created',
        orderId: null,
        serviceId: null,
        to: 'a@test.example',
        cc: null,
        bcc: null,
        subject: 'Test',
        message: '<p>Hi</p>',
    );

    expect($job->tries)->toBe(5)
        ->and($job->backoff)->toBe([60, 120, 300, 600]);
});

test('billing email cc only includes owner and billing contacts', function () {
    Queue::fake();
    $this->seed(EmailTemplateSeeder::class);

    $client = Client::query()->create([
        'name' => 'Roles Client',
        'email' => 'roles-client@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    foreach ([
        ContactRole::Owner->value => 'owner@test.example',
        ContactRole::Billing->value => 'billing@test.example',
        ContactRole::Technical->value => 'tech@test.example',
        ContactRole::Other->value => 'other@test.example',
    ] as $role => $email) {
        Contact::query()->create([
            'client_id' => $client->id,
            'name' => ucfirst($role).' Contact',
            'email' => $email,
            'role' => ContactRole::from($role),
            'is_primary' => false,
        ]);
    }

    expect($client->billingEmailRecipients())->toBe([
        'roles-client@test.example',
        'owner@test.example',
        'billing@test.example',
    ])->and($client->load('contacts')->billingEmailRecipients())->toBe([
        'roles-client@test.example',
        'owner@test.example',
        'billing@test.example',
    ]);

    app(BillingEmailSender::class)->sendToClient(
        EmailTemplate::query()->where('slug', 'order_created')->firstOrFail(),
        $client->email,
        ['client_name' => $client->name, 'order_number' => 'ORD-ROLES'],
        $client->id,
    );

    Queue::assertPushed(SendClientBillingEmailJob::class, function (SendClientBillingEmailJob $job) {
        $cc = (string) $job->cc;

        return $job->to === 'roles-client@test.example'
            && str_contains($cc, 'owner@test.example')
            && str_contains($cc, 'billing@test.example')
            && ! str_contains($cc, 'tech@test.example')
            && ! str_contains($cc, 'other@test.example');
    });
});
