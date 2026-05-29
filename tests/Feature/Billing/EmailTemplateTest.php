<?php

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Billing\Orders\Manage as OrdersManage;
use App\Livewire\Billing\Services\Manage as ServicesManage;
use App\Mail\BillingTemplateMail;
use App\Models\BillingTaxSetting;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('email template seeder creates example templates', function () {
    $this->seed(EmailTemplateSeeder::class);

    expect(EmailTemplate::query()->count())->toBe(5);

    $orderCreated = EmailTemplate::query()->where('slug', 'order_created')->first();

    expect($orderCreated)->not->toBeNull()
        ->and($orderCreated->name)->toBe('Encomenda criada')
        ->and($orderCreated->variables)->toContain('client_name', 'order_number')
        ->and($orderCreated->is_active)->toBeTrue();
});

test('service billing interval normalizes recurring to monthly and yearly', function () {
    $client = \App\Models\Client::query()->create([
        'name' => 'Interval Co',
        'email' => 'interval@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = \App\Models\Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Web',
        'slug' => 'web-interval',
        'is_active' => true,
    ]);
    $service = \App\Models\Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'currency' => 'EUR',
        'recurring_amount_minor' => 30_00,
        'interval_unit' => \App\Enums\IntervalUnit::Month,
        'interval_count' => 3,
        'status' => \App\Enums\ServiceStatus::Active,
    ]);

    expect($service->monthlyAmountMinor())->toBe(10_00)
        ->and($service->yearlyAmountMinor())->toBe(120_00)
        ->and($service->billingIntervalLabel())->toContain('3');
});

test('service welcome email variables include credentials', function () {
    $client = \App\Models\Client::query()->create([
        'name' => 'Acme',
        'email' => 'acme@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = \App\Models\Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Web S',
        'slug' => 'web-s',
        'is_active' => true,
    ]);
    $server = \App\Models\Server::query()->create([
        'name' => 'Node 1',
        'hostname' => 'node1.test.example',
        'panel_url' => 'https://panel.test.example',
        'status' => \App\Enums\ServerStatus::Active,
        'cost_amount_minor' => 100_00,
        'cost_currency' => 'EUR',
    ]);
    $service = \App\Models\Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'username' => 'acme_web',
        'password' => 's3cret-pass',
        'currency' => 'EUR',
        'status' => \App\Enums\ServiceStatus::Active,
    ]);

    $vars = $service->welcomeEmailVariables();

    expect($vars['username'])->toBe('acme_web')
        ->and($vars['password'])->toBe('s3cret-pass')
        ->and($vars['product_name'])->toBe('Web S')
        ->and($vars['panel_url'])->toBe('https://panel.test.example');
});

test('order email variables include issued date', function () {
    $client = Client::query()->create([
        'name' => 'Date Client',
        'email' => 'date@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-DATE-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 10_00,
        'tax_minor' => 0,
        'total_minor' => 10_00,
        'issued_at' => '2026-05-10 14:30:00',
        'due_at' => '2026-05-20',
    ]);

    expect($order->emailVariables()['order_issued_date'])->toBe('2026-05-10');
});

test('order email variables include html line descriptions', function () {
    $client = Client::query()->create([
        'name' => 'Lines Client',
        'email' => 'lines@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-LINES-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 50_00,
        'tax_minor' => 0,
        'total_minor' => 50_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
    ]);
    $order->items()->create([
        'position' => 0,
        'type' => \App\Enums\OrderItemType::Service,
        'description' => 'Hosting - example.pt (01/06/2026 - 01/07/2026)',
        'quantity' => 1,
        'unit_amount_minor' => 30_00,
        'tax_amount_minor' => 0,
    ]);
    $order->items()->create([
        'position' => 1,
        'type' => \App\Enums\OrderItemType::Domain,
        'description' => 'Renovação de Domínio - example.pt (01/06/2026 - 01/06/2027)',
        'quantity' => 1,
        'unit_amount_minor' => 20_00,
        'tax_amount_minor' => 0,
    ]);

    $lines = $order->emailVariables()['order_lines'];

    expect($lines)
        ->toContain('Hosting - example.pt (01/06/2026 - 01/07/2026) - 30.00 EUR')
        ->toContain('Renovação de Domínio - example.pt (01/06/2026 - 01/06/2027) - 20.00 EUR')
        ->toContain('<table');
});

test('order email variables include totals breakdown html', function () {
    BillingTaxSetting::query()->delete();
    BillingTaxSetting::query()->create(['vat_rate_percent' => 23, 'irs_rate_percent' => 25]);

    $client = Client::query()->create([
        'name' => 'Totals Client',
        'email' => 'totals@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-TOTALS-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 100_00,
        'tax_minor' => 23_00,
        'irs_minor' => 25_00,
        'total_minor' => 98_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
    ]);

    $totals = $order->emailVariables()['order_totals'];

    expect($totals)
        ->toContain('100.00 EUR')
        ->toContain('23.00 EUR')
        ->toContain('25.00 EUR')
        ->toContain('98.00 EUR')
        ->toContain(__('Subtotal'))
        ->toContain(__('Total'));
});

test('order_created template renders order line items', function () {
    $this->seed(EmailTemplateSeeder::class);

    $client = Client::query()->create([
        'name' => 'Render Lines',
        'email' => 'render-lines@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-RENDER-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 10_00,
        'tax_minor' => 0,
        'total_minor' => 10_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
    ]);
    $order->items()->create([
        'position' => 0,
        'type' => \App\Enums\OrderItemType::Manual,
        'description' => 'Linha de teste',
        'quantity' => 1,
        'unit_amount_minor' => 10_00,
        'tax_amount_minor' => 0,
    ]);

    $template = EmailTemplate::query()->where('slug', 'order_created')->firstOrFail();
    $rendered = $template->render($order->emailVariables());

    expect($rendered['body'])
        ->toContain('Linha de teste - 10.00 EUR')
        ->toContain(__('Subtotal'))
        ->toContain('10.00 EUR');
});

test('order_payment_reminder template renders order totals after line items', function () {
    $this->seed(EmailTemplateSeeder::class);

    $client = Client::query()->create([
        'name' => 'Reminder Totals',
        'email' => 'reminder-totals@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-REM-TOT',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 50_00,
        'tax_minor' => 11_50,
        'irs_minor' => 12_50,
        'total_minor' => 49_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(3),
    ]);
    $order->items()->create([
        'position' => 0,
        'type' => \App\Enums\OrderItemType::Manual,
        'description' => 'Serviço mensal',
        'quantity' => 1,
        'unit_amount_minor' => 50_00,
        'tax_amount_minor' => 0,
    ]);

    $template = EmailTemplate::query()->where('slug', 'order_payment_reminder')->firstOrFail();
    $rendered = $template->render($order->emailVariables());

    $linesPos = strpos($rendered['body'], 'Serviço mensal - 50.00 EUR');
    $totalsPos = strpos($rendered['body'], __('Subtotal'));
    $paymentPos = strpos($rendered['body'], 'Dados para pagamento');

    expect($linesPos)->not->toBeFalse()
        ->and($totalsPos)->not->toBeFalse()
        ->and($paymentPos)->not->toBeFalse()
        ->and($linesPos)->toBeLessThan($totalsPos)
        ->and($totalsPos)->toBeLessThan($paymentPos)
        ->and($rendered['body'])->toContain('49.00 EUR');
});

test('email template render replaces placeholders', function () {
    $template = EmailTemplate::query()->create([
        'slug' => 'test_render',
        'name' => 'Test',
        'subject' => 'Hello {{client_name}}',
        'body' => '<p>Order {{order_number}}</p>',
        'variables' => ['client_name', 'order_number'],
        'is_active' => true,
    ]);

    $rendered = $template->render([
        'client_name' => 'Acme',
        'order_number' => 'ORD-001',
    ]);

    expect($rendered['subject'])->toBe('Hello Acme')
        ->and($rendered['body'])->toBe('<p>Order ORD-001</p>');
});

test('unpaid order edit hides order paid template from selector', function () {
    $this->seed(EmailTemplateSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Unpaid Client',
        'email' => 'unpaid@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-UNPAID-1',
        'status' => OrderStatus::Unpaid,
        'currency' => 'EUR',
        'subtotal_minor' => 10_00,
        'tax_minor' => 0,
        'total_minor' => 10_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
    ]);

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->assertSee('Encomenda criada', false)
        ->assertDontSee('Encomenda paga', false);
});

test('service edit can send welcome email to client', function () {
    Mail::fake();
    $this->seed(EmailTemplateSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Welcome Client',
        'email' => 'welcome-client@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $product = \App\Models\Product::query()->create([
        'type' => \App\Enums\ProductType::Hosting,
        'name' => 'Web M',
        'slug' => 'web-m-welcome',
        'is_active' => true,
    ]);
    $server = \App\Models\Server::query()->create([
        'name' => 'Panel Node',
        'hostname' => 'panel.test.example',
        'panel_url' => 'https://panel.test.example',
        'status' => \App\Enums\ServerStatus::Active,
        'cost_amount_minor' => 50_00,
        'cost_currency' => 'EUR',
    ]);
    $service = \App\Models\Service::query()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'username' => 'welcome_user',
        'password' => 'welcome-pass',
        'associated_domain' => 'example.test',
        'currency' => 'EUR',
        'interval_unit' => \App\Enums\IntervalUnit::Month,
        'interval_count' => 1,
        'status' => \App\Enums\ServiceStatus::Active,
    ]);

    Livewire::test(ServicesManage::class, ['service' => $service])
        ->set('email_template_slug', 'service_welcome')
        ->call('sendEmail')
        ->assertHasNoErrors();

    Mail::assertSent(BillingTemplateMail::class, function (BillingTemplateMail $mail) {
        return $mail->hasTo('welcome-client@test.example')
            && str_contains($mail->mailSubject, 'Web M')
            && str_contains($mail->htmlBody, 'welcome_user')
            && str_contains($mail->htmlBody, 'https://panel.test.example');
    });
});

test('order edit can send order paid email to client', function () {
    Mail::fake();
    $this->seed(EmailTemplateSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Email Client',
        'email' => 'billing-client@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-EMAIL-1',
        'status' => OrderStatus::Paid,
        'currency' => 'EUR',
        'subtotal_minor' => 99_00,
        'tax_minor' => 0,
        'total_minor' => 99_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => '2026-03-15',
    ]);
    $transaction = Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 99_00,
        'paid_at' => '2026-03-15',
    ]);

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->set('email_template_slug', 'order_paid')
        ->call('sendEmail')
        ->assertHasNoErrors();

    Mail::assertSent(BillingTemplateMail::class, function (BillingTemplateMail $mail) {
        return $mail->hasTo('billing-client@test.example')
            && str_contains($mail->mailSubject, 'ORD-EMAIL-1')
            && str_contains($mail->htmlBody, 'Email Client');
    });
});

test('order paid email includes attachment notice when payment has a document', function () {
    Mail::fake();
    Storage::fake('local');
    $this->seed(EmailTemplateSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $client = Client::query()->create([
        'name' => 'Anexo Cliente',
        'email' => 'anexo@test.example',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'client_id' => $client->id,
        'number' => 'ORD-ANEXO-1',
        'status' => OrderStatus::Paid,
        'currency' => 'EUR',
        'subtotal_minor' => 50_00,
        'tax_minor' => 0,
        'total_minor' => 50_00,
        'issued_at' => now(),
        'due_at' => now()->addDays(7),
        'paid_at' => '2026-03-15',
    ]);
    $transaction = Transaction::query()->create([
        'order_id' => $order->id,
        'status' => TransactionStatus::Completed,
        'currency' => 'EUR',
        'amount_minor' => 50_00,
        'paid_at' => '2026-03-15',
        'document_path' => 'transaction-documents/1/receipt.pdf',
        'document_name' => 'receipt.pdf',
    ]);
    Storage::disk('local')->put($transaction->document_path, 'pdf-content');

    Livewire::test(OrdersManage::class, ['order' => $order])
        ->set('email_template_slug', 'order_paid')
        ->call('sendEmail')
        ->assertHasNoErrors();

    Mail::assertSent(BillingTemplateMail::class, function (BillingTemplateMail $mail) {
        return str_contains($mail->htmlBody, 'Segue em anexo o recibo do pagamento efetuado')
            && count($mail->fileAttachments) === 1;
    });
});
