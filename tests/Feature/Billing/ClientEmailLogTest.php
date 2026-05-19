<?php

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\ServiceStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Billing\Clients\Show as ClientsShow;
use App\Livewire\Billing\Orders\Manage as OrdersManage;
use App\Models\Client;
use App\Models\ClientEmailLog;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('sending order email creates a client email log', function () {
    Mail::fake();
    Storage::fake('local');
    $this->seed(EmailTemplateSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Log Client',
        'email' => 'log-client@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-LOG-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 50_00,
        'tax_minor' => 0,
        'irs_minor' => 0,
        'total_minor' => 50_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
    ]);

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->set('email_template_slug', 'order_created')
        ->call('sendEmail')
        ->assertHasNoErrors();

    $log = ClientEmailLog::query()->where('client_id', $client->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->subject)->toContain('ORD-LOG-1')
        ->and($log->to_address)->toBe('log-client@test.example')
        ->and($log->email_template_slug)->toBe('order_created')
        ->and($log->order_id)->toBe($order->id)
        ->and($log->message)->not->toBe('');
});

test('order paid email log stores attachment copy', function () {
    Storage::fake('local');
    $this->seed(EmailTemplateSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Attach Client',
        'email' => 'attach@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-ATTACH-1',
        'status' => OrderStatus::Paid,
        'currency' => 'EUR',
        'subtotal_minor' => 50_00,
        'tax_minor' => 0,
        'irs_minor' => 0,
        'total_minor' => 50_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => now(),
    ]);
    $transaction = Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 50_00,
        'paid_at' => now(),
        'document_path' => 'transaction-documents/1/receipt.pdf',
        'document_name' => 'receipt.pdf',
    ]);
    Storage::disk('local')->put($transaction->document_path, 'pdf-content');

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->set('email_template_slug', 'order_paid')
        ->call('sendEmail')
        ->assertHasNoErrors();

    $log = ClientEmailLog::query()->where('client_id', $client->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->hasAttachments())->toBeTrue();

    $path = $log->attachmentFiles()[0]['path'];
    Storage::disk('local')->assertExists($path);

    $this->get(route('billing.clients.email-log-attachment', [$log, 0]))
        ->assertOk()
        ->assertDownload('receipt.pdf');
});

test('client show emails tab lists sent emails', function () {
    Mail::fake();
    $this->seed(EmailTemplateSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Tab Client',
        'email' => 'tab@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'type' => ProductType::Hosting,
        'name' => 'Web',
        'slug' => 'web-log-tab',
        'is_active' => true,
    ]);
    $service = Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'status' => ServiceStatus::Active,
    ]);

    ClientEmailLog::query()->create([
        'client_id' => $client->id,
        'subject' => 'Welcome email',
        'message' => '<p>Hello</p>',
        'to_address' => 'tab@test.example',
        'cc' => 'cc@test.example',
        'bcc' => null,
        'attachments' => null,
        'sent_at' => now(),
    ]);

    $logId = ClientEmailLog::query()->first()->id;

    Livewire::test(ClientsShow::class, ['client' => $client])
        ->set('tab', 'emails')
        ->assertSee('Welcome email', false)
        ->assertSee('tab@test.example', false)
        ->call('viewEmailLog', $logId)
        ->assertSet('showEmailLogModal', true)
        ->assertSet('viewingEmailLogId', $logId)
        ->assertSee('cc@test.example', false)
        ->assertSee('Hello', false);
});
