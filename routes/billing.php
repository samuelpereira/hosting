<?php

use App\Livewire\Billing\Clients\Index as ClientsIndex;
use App\Livewire\Billing\Clients\Manage as ClientsManage;
use App\Livewire\Billing\Clients\Show as ClientsShow;
use App\Livewire\Billing\Domains\Index as DomainsIndex;
use App\Livewire\Billing\Domains\Manage as DomainsManage;
use App\Livewire\Billing\EmailTemplates\Index as EmailTemplatesIndex;
use App\Livewire\Billing\EmailTemplates\Manage as EmailTemplatesManage;
use App\Livewire\Billing\Orders\Index as OrdersIndex;
use App\Livewire\Billing\Orders\Manage as OrdersManage;
use App\Livewire\Billing\Products\Index as ProductsIndex;
use App\Livewire\Billing\Products\Manage as ProductsManage;
use App\Livewire\Billing\Products\Show as ProductsShow;
use App\Livewire\Billing\Registrars\Index as RegistrarsIndex;
use App\Livewire\Billing\Registrars\Manage as RegistrarsManage;
use App\Livewire\Billing\RegistrarTlds\Index as RegistrarTldsIndex;
use App\Livewire\Billing\RegistrarTlds\Manage as RegistrarTldsManage;
use App\Livewire\Billing\Servers\Index as ServersIndex;
use App\Livewire\Billing\Servers\Manage as ServersManage;
use App\Livewire\Billing\Servers\Show as ServersShow;
use App\Livewire\Billing\AutomationSettings\Manage as AutomationSettingsManage;
use App\Livewire\Billing\CompanySettings\Manage as CompanySettingsManage;
use App\Livewire\Billing\TaxSettings\Manage as TaxSettingsManage;
use App\Livewire\Billing\Services\Index as ServicesIndex;
use App\Livewire\Billing\Services\Manage as ServicesManage;
use App\Http\Controllers\Billing\ClientEmailLogAttachmentController;
use App\Http\Controllers\Billing\TransactionDocumentController;
use App\Livewire\Billing\Transactions\Index as TransactionsIndex;
use App\Livewire\Billing\Transactions\Manage as TransactionsManage;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    Route::livewire('clients', ClientsIndex::class)->name('clients.index');
    Route::livewire('clients/create', ClientsManage::class)->name('clients.create');
    Route::livewire('clients/{client}', ClientsShow::class)->name('clients.show');
    Route::livewire('clients/{client}/edit', ClientsManage::class)->name('clients.edit');
    Route::get('clients/email-logs/{emailLog}/attachments/{index}', ClientEmailLogAttachmentController::class)
        ->whereNumber('index')
        ->name('clients.email-log-attachment');

    Route::livewire('products', ProductsIndex::class)->name('products.index');
    Route::livewire('products/create', ProductsManage::class)->name('products.create');
    Route::livewire('products/{product}', ProductsShow::class)->name('products.show');
    Route::livewire('products/{product}/edit', ProductsManage::class)->name('products.edit');

    Route::livewire('servers', ServersIndex::class)->name('servers.index');
    Route::livewire('servers/create', ServersManage::class)->name('servers.create');
    Route::livewire('servers/{server}', ServersShow::class)->name('servers.show');
    Route::livewire('servers/{server}/edit', ServersManage::class)->name('servers.edit');

    Route::livewire('services', ServicesIndex::class)->name('services.index');
    Route::livewire('services/create', ServicesManage::class)->name('services.create');
    Route::livewire('services/{service}/edit', ServicesManage::class)->name('services.edit');

    Route::livewire('registrars', RegistrarsIndex::class)->name('registrars.index');
    Route::livewire('registrars/create', RegistrarsManage::class)->name('registrars.create');
    Route::livewire('registrars/{registrar}/edit', RegistrarsManage::class)->name('registrars.edit');

    Route::livewire('registrar-tlds', RegistrarTldsIndex::class)->name('registrar-tlds.index');
    Route::livewire('registrar-tlds/create', RegistrarTldsManage::class)->name('registrar-tlds.create');
    Route::livewire('registrar-tlds/{registrarTld}/edit', RegistrarTldsManage::class)->name('registrar-tlds.edit');

    Route::livewire('domains', DomainsIndex::class)->name('domains.index');
    Route::livewire('domains/create', DomainsManage::class)->name('domains.create');
    Route::livewire('domains/{domain}/edit', DomainsManage::class)->name('domains.edit');

    Route::livewire('orders', OrdersIndex::class)->name('orders.index');
    Route::livewire('orders/create', OrdersManage::class)->name('orders.create');
    Route::livewire('orders/{order}/edit', OrdersManage::class)->name('orders.edit');

    Route::livewire('transactions', TransactionsIndex::class)->name('transactions.index');
    Route::livewire('transactions/create', TransactionsManage::class)->name('transactions.create');
    Route::livewire('transactions/{transaction}/edit', TransactionsManage::class)->name('transactions.edit');
    Route::get('transactions/{transaction}/document', TransactionDocumentController::class)->name('transactions.document');

    Route::livewire('company', CompanySettingsManage::class)->name('company.edit');

    Route::livewire('tax-settings', TaxSettingsManage::class)->name('tax-settings.edit');

    Route::livewire('automation', AutomationSettingsManage::class)->name('automation.edit');

    Route::livewire('email-templates', EmailTemplatesIndex::class)->name('email-templates.index');
    Route::livewire('email-templates/create', EmailTemplatesManage::class)->name('email-templates.create');
    Route::livewire('email-templates/{template}/edit', EmailTemplatesManage::class)->name('email-templates.edit');
});
