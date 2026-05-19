<?php

namespace App\Livewire\Billing\Services;

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Enums\ProductPriceBillingKind;
use App\Enums\RegistrarTldPriceOperation;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailTemplate;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\RegistrarTldPrice;
use App\Models\Server;
use App\Models\Service;
use App\Services\Billing\BillingEmailSender;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public ?Service $service = null;

    public ?int $client_id = null;

    public ?int $product_id = null;

    public string $username = '';

    public string $service_password = '';

    public ?int $server_id = null;

    public string $currency = 'EUR';

    public string $first_payment_amount_input = '';

    public string $recurring_amount_input = '';

    public string $interval_unit = 'month';

    public int $interval_count = 1;

    public string $status = 'pending';

    public ?string $activated_at = null;

    public ?string $due_at = null;

    public ?string $cancelled_at = null;

    public ?int $allocated_ram_mb = null;

    public ?int $allocated_disk_gb = null;

    public string $notes = '';

    public string $associated_domain = '';

    public bool $create_domain = false;

    public ?int $domain_registrar_id = null;

    public ?int $domain_registrar_tld_id = null;

    public string $domain_fqdn = '';

    public string $domain_registered_at = '';

    public string $domain_expires_at = '';

    public string $domain_due_at = '';

    public bool $domain_auto_renew = false;

    public string $domain_status = 'active';

    public string $domain_currency = 'EUR';

    public string $domain_first_payment_amount_input = '';

    public string $domain_recurring_amount_input = '';

    public string $domain_cost_input = '';

    public string $domain_interval_unit = 'year';

    public int $domain_interval_count = 1;

    public string $domain_registrar_price_operation = 'register';

    public ?int $domain_registrar_tld_price_id = null;

    public bool $show_move_service = false;

    public string $move_client_search = '';

    public ?int $move_target_client_id = null;

    public string $email_template_slug = 'service_welcome';

    public function mount(?Service $service = null): void
    {
        $this->service = $service?->load([
            'client',
            'domain',
            'orders' => fn ($q) => $q->orderByDesc('id'),
        ]);
        if ($service) {
            $this->client_id = $service->client_id;
            $this->product_id = $service->product_id;
            $this->username = (string) ($service->username ?? '');
            $this->service_password = (string) ($service->password ?? '');
            $this->server_id = $service->server_id;
            $this->currency = $service->currency;
            $this->first_payment_amount_input = $service->first_payment_amount_minor !== null
                ? number_format($service->first_payment_amount_minor / 100, 2, '.', '')
                : '';
            $this->recurring_amount_input = $service->recurring_amount_minor !== null
                ? number_format($service->recurring_amount_minor / 100, 2, '.', '')
                : '';
            $this->interval_unit = $service->interval_unit->value;
            $this->interval_count = max(1, (int) $service->interval_count);
            $this->status = $service->status->value;
            $this->activated_at = $service->activated_at?->format('Y-m-d');
            $this->due_at = $service->due_at?->format('Y-m-d');
            $this->cancelled_at = $service->cancelled_at?->format('Y-m-d');
            $this->allocated_ram_mb = $service->allocated_ram_mb;
            $this->allocated_disk_gb = $service->allocated_disk_gb;
            $this->notes = (string) ($service->notes ?? '');
            $this->associated_domain = (string) ($service->associated_domain ?? '');
            if ($service->domain) {
                $this->associated_domain = $this->associated_domain !== ''
                    ? $this->associated_domain
                    : $service->domain->fqdn;
            }
            $this->create_domain = false;
        } else {
            $cid = request()->integer('client_id');
            $this->client_id = $cid > 0 ? $cid : null;
            $pid = request()->integer('product_id');
            $this->product_id = $pid > 0 ? $pid : null;
            $sid = request()->integer('server_id');
            $this->server_id = $sid > 0 ? $sid : null;
            if ($this->product_id) {
                $this->applyProductPricing();
            }
            if ($this->client_id) {
                $this->applyClientCurrency();
            }
            $this->domain_registered_at = now()->format('Y-m-d');
            $this->domain_expires_at = now()->addYear()->format('Y-m-d');
            $this->domain_due_at = now()->addYear()->format('Y-m-d');
            $this->applyDefaultActivationDates();
        }
    }

    public function updatedCreateDomain(bool $value): void
    {
        if (! $value || $this->service?->domain_id) {
            return;
        }
        if ($this->domain_registered_at === '') {
            $this->domain_registered_at = now()->format('Y-m-d');
        }
        if ($this->domain_expires_at === '') {
            $this->domain_expires_at = now()->addYear()->format('Y-m-d');
        }
        if ($this->domain_due_at === '') {
            $this->domain_due_at = $this->domain_expires_at;
        }
    }

    public function updatedProductId(mixed $value): void
    {
        $pid = (int) $value;
        if ($pid <= 0) {
            return;
        }
        $product = Product::query()
            ->whereKey($pid)
            ->first(['id', 'default_allocated_ram_mb', 'default_allocated_disk_gb']);
        if (! $product) {
            return;
        }
        if ($product->default_allocated_ram_mb !== null) {
            $this->allocated_ram_mb = (int) $product->default_allocated_ram_mb;
        }
        if ($product->default_allocated_disk_gb !== null) {
            $this->allocated_disk_gb = (int) $product->default_allocated_disk_gb;
        }

        $this->applyProductPricing();
    }

    public function updatedCurrency(): void
    {
        if ($this->service !== null) {
            return;
        }

        $this->applyProductPricing();
    }

    public function updatedIntervalUnit(): void
    {
        $this->applyProductPricing(recurringOnly: true);
        $this->applyDefaultDueAt();
    }

    public function updatedIntervalCount(): void
    {
        $this->applyProductPricing(recurringOnly: true);
        $this->applyDefaultDueAt();
    }

    public function updatedDomainRegistrarId(): void
    {
        $this->domain_registrar_tld_id = null;
        $this->domain_registrar_tld_price_id = null;
        $this->domain_registrar_price_operation = RegistrarTldPriceOperation::Register->value;
    }

    public function updatedDomainRegistrarTldId(): void
    {
        $this->domain_registrar_tld_price_id = null;
        $this->applyDomainBillingIntervalFromRegistrar();
    }

    public function updatedDomainRegistrarPriceOperation(): void
    {
        $this->domain_registrar_tld_price_id = null;
    }

    public function updatedDomainRegistrarTldPriceId(mixed $value): void
    {
        $priceId = (int) $value;
        if ($priceId <= 0) {
            return;
        }
        $price = RegistrarTldPrice::query()->find($priceId);
        if (! $price) {
            return;
        }
        $this->domain_currency = $price->currency;
        if (trim($this->domain_first_payment_amount_input) === '') {
            $this->domain_first_payment_amount_input = number_format($price->amount_minor / 100, 2, '.', '');
        }
        if ($price->domain_cost_minor !== null) {
            $this->domain_cost_input = number_format($price->domain_cost_minor / 100, 2, '.', '');
        }
        $this->applyDomainBillingIntervalFromRegistrar($price);
    }

    public function updatedClientId(mixed $value): void
    {
        if ($this->service !== null) {
            return;
        }
        $cid = (int) $value;
        if ($cid <= 0) {
            return;
        }
        $this->applyClientCurrency();
    }

    public function updatedShowMoveService(bool $value): void
    {
        if (! $value) {
            $this->move_client_search = '';
            $this->move_target_client_id = null;
        }
    }

    public function selectMoveTargetClient(int $clientId): void
    {
        if ($this->service === null || $clientId === $this->service->client_id) {
            return;
        }

        $this->move_target_client_id = $clientId;
    }

    public function moveService(): void
    {
        if ($this->service === null) {
            return;
        }

        $this->validate([
            'move_target_client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id'),
                Rule::notIn([$this->service->client_id]),
            ],
        ]);

        $newClient = Client::query()->findOrFail($this->move_target_client_id);

        DB::transaction(function () use ($newClient) {
            $updates = [
                'client_id' => $newClient->id,
                'currency' => $newClient->currency,
            ];

            if ($this->service->domain_id !== null) {
                $domainClientId = Domain::query()
                    ->whereKey($this->service->domain_id)
                    ->value('client_id');

                if ((int) $domainClientId !== (int) $newClient->id) {
                    $updates['domain_id'] = null;
                }
            }

            $this->service->update($updates);
        });

        $this->client_id = $newClient->id;
        $this->currency = $newClient->currency;
        $this->service->load([
            'client',
            'domain',
            'orders' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $this->show_move_service = false;
        $this->move_client_search = '';
        $this->move_target_client_id = null;

        Flux::toast(variant: 'success', text: __('Service moved to :client.', ['client' => $newClient->name]));
    }

    public function save(): void
    {
        $this->client_id = (int) $this->client_id;
        $this->product_id = (int) $this->product_id;
        $this->server_id = $this->server_id ? (int) $this->server_id : null;

        $amountRule = ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'];

        $rules = [
            'product_id' => ['required', 'exists:products,id'],
            'username' => ['nullable', 'string', 'max:255'],
            'service_password' => ['nullable', 'string', 'max:255'],
            'server_id' => ['nullable', 'exists:servers,id'],
            'currency' => ['required', 'string', 'size:3'],
            'first_payment_amount_input' => $amountRule,
            'recurring_amount_input' => $amountRule,
            'interval_unit' => ['required', Rule::enum(IntervalUnit::class)],
            'interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'status' => ['required', Rule::enum(ServiceStatus::class)],
            'activated_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'allocated_ram_mb' => ['nullable', 'integer', 'min:0'],
            'allocated_disk_gb' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'associated_domain' => ['nullable', 'string', 'max:255'],
            'create_domain' => ['boolean'],
        ];

        if ($this->service === null) {
            $rules['client_id'] = ['required', 'exists:clients,id'];
        }

        $this->validate($rules);

        $canRegisterDomain = ! $this->service?->domain_id;
        if ($this->create_domain && $canRegisterDomain) {
            $this->domain_registrar_id = (int) $this->domain_registrar_id;
            $this->domain_registrar_tld_id = $this->domain_registrar_tld_id ? (int) $this->domain_registrar_tld_id : null;
            $this->domain_registrar_tld_price_id = $this->domain_registrar_tld_price_id ? (int) $this->domain_registrar_tld_price_id : null;
            $this->validate([
                'domain_registrar_id' => ['required', 'exists:registrars,id'],
                'domain_registrar_tld_id' => ['required', 'exists:registrar_tlds,id'],
                'domain_registrar_price_operation' => ['required', Rule::enum(RegistrarTldPriceOperation::class)],
                'domain_registrar_tld_price_id' => [
                    'required',
                    'integer',
                    Rule::exists('registrar_tld_prices', 'id')->where(function ($q) {
                        $q->where('registrar_tld_id', $this->domain_registrar_tld_id)
                            ->where('operation', $this->domain_registrar_price_operation)
                            ->where('is_active', true);
                    }),
                ],
                'domain_fqdn' => [
                    'required',
                    'string',
                    'max:255',
                    'lowercase',
                    Rule::unique('domains', 'fqdn')
                        ->where('client_id', $this->client_id)
                        ->ignore($this->service?->domain_id),
                ],
                'domain_registered_at' => ['required', 'date'],
                'domain_expires_at' => ['required', 'date'],
                'domain_due_at' => ['required', 'date'],
                'domain_auto_renew' => ['boolean'],
                'domain_status' => ['required', Rule::enum(DomainStatus::class)],
                'domain_currency' => ['required', 'string', 'size:3'],
                'domain_first_payment_amount_input' => $amountRule,
                'domain_recurring_amount_input' => $amountRule,
                'domain_cost_input' => $amountRule,
                'domain_interval_unit' => ['required', Rule::enum(IntervalUnit::class)],
                'domain_interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            ]);
            $tld = RegistrarTld::query()->findOrFail($this->domain_registrar_tld_id);
            if ((int) $tld->registrar_id !== $this->domain_registrar_id) {
                $this->addError('domain_registrar_tld_id', __('TLD must belong to the selected registrar.'));

                return;
            }
        }

        if ($this->server_id && $this->allocated_ram_mb !== null) {
            $server = Server::query()->findOrFail($this->server_id);
            $cap = $server->capacityRamMb();
            if ($cap !== null) {
                $used = (int) $server->allocatingServices()
                    ->when($this->service, fn ($q) => $q->where('id', '!=', $this->service->id))
                    ->sum('allocated_ram_mb');
                if ($used + $this->allocated_ram_mb > $cap) {
                    $this->addError('allocated_ram_mb', __('RAM allocation exceeds server capacity.'));

                    return;
                }
            }
        }
        if ($this->server_id && $this->allocated_disk_gb !== null) {
            $server = Server::query()->findOrFail($this->server_id);
            $cap = $server->capacityDiskGb();
            if ($cap !== null) {
                $used = (int) $server->allocatingServices()
                    ->when($this->service, fn ($q) => $q->where('id', '!=', $this->service->id))
                    ->sum('allocated_disk_gb');
                if ($used + $this->allocated_disk_gb > $cap) {
                    $this->addError('allocated_disk_gb', __('Disk allocation exceeds server capacity.'));

                    return;
                }
            }
        }

        $domainId = $this->service?->domain_id;
        $isNew = $this->service === null;

        DB::transaction(function () use (&$domainId, $canRegisterDomain, $isNew) {
            if ($this->create_domain && $canRegisterDomain) {
                $fqdn = strtolower(trim($this->domain_fqdn));
                $domain = Domain::query()->create([
                    'client_id' => $this->client_id,
                    'registrar_id' => $this->domain_registrar_id,
                    'registrar_tld_id' => $this->domain_registrar_tld_id,
                    'registrar_tld_price_id' => $this->domain_registrar_tld_price_id,
                    'fqdn' => $fqdn,
                    'status' => DomainStatus::from($this->domain_status),
                    'currency' => strtoupper($this->domain_currency),
                    'first_payment_amount_minor' => $this->parseAmountMinor($this->domain_first_payment_amount_input),
                    'recurring_amount_minor' => $this->parseAmountMinor($this->domain_recurring_amount_input),
                    'domain_cost_minor' => $this->resolveDomainCostMinorFromSelectedPrice(),
                    'interval_unit' => IntervalUnit::from($this->domain_interval_unit),
                    'interval_count' => max(1, (int) $this->domain_interval_count),
                    'registered_at' => $this->domain_registered_at,
                    'expires_at' => $this->domain_expires_at,
                    'due_at' => $this->domain_due_at,
                    'auto_renew' => $this->domain_auto_renew,
                    'notes' => null,
                ]);
                $domainId = $domain->id;
            }

            $associated = trim($this->associated_domain) !== ''
                ? strtolower(trim($this->associated_domain))
                : null;
            if ($domainId) {
                $fqdnFromDb = Domain::query()->whereKey($domainId)->value('fqdn');
                if ($fqdnFromDb) {
                    $associated = $fqdnFromDb;
                }
            }

            $payload = [
                'client_id' => $this->client_id,
                'product_id' => $this->product_id,
                'username' => trim($this->username) !== '' ? trim($this->username) : null,
                'currency' => strtoupper($this->currency),
                'first_payment_amount_minor' => $this->parseAmountMinor($this->first_payment_amount_input),
                'recurring_amount_minor' => $this->parseAmountMinor($this->recurring_amount_input),
                'interval_unit' => IntervalUnit::from($this->interval_unit),
                'interval_count' => max(1, (int) $this->interval_count),
                'server_id' => $this->server_id,
                'domain_id' => $domainId,
                'associated_domain' => $associated,
                'status' => ServiceStatus::from($this->status),
                'activated_at' => $this->activated_at,
                'due_at' => $this->due_at,
                'cancelled_at' => $this->cancelled_at,
                'allocated_ram_mb' => $this->allocated_ram_mb,
                'allocated_disk_gb' => $this->allocated_disk_gb,
                'notes' => $this->notes !== '' ? $this->notes : null,
            ];

            if (trim($this->service_password) !== '') {
                $payload['password'] = trim($this->service_password);
            } elseif ($isNew) {
                $payload['password'] = null;
            }

            if ($isNew) {
                $this->service = Service::query()->create($payload);
            } else {
                $this->service->update($payload);
                $this->service->refresh();
            }
        });

        $this->service?->load([
            'client',
            'domain',
            'orders' => fn ($q) => $q->orderByDesc('id'),
        ]);

        Flux::toast(variant: 'success', text: $isNew ? __('Service created.') : __('Service updated.'));
        $this->redirect(route('billing.services.edit', $this->service), navigate: true);
    }

    private function applyClientCurrency(): void
    {
        $client = Client::query()->find($this->client_id);
        if ($client) {
            $this->currency = $client->currency;
            $this->domain_currency = $client->currency;
        }

        if ($this->service === null && $this->product_id) {
            $this->applyProductPricing();
        }
    }

    private function applyProductPricing(bool $recurringOnly = false): void
    {
        if (! $this->product_id) {
            return;
        }

        if ($this->service !== null && ! $recurringOnly) {
            return;
        }

        $currency = strtoupper($this->currency);
        $prices = ProductPrice::query()
            ->where('product_id', $this->product_id)
            ->where('is_active', true)
            ->where('currency', $currency)
            ->get();

        if ($prices->isEmpty()) {
            $prices = ProductPrice::query()
                ->where('product_id', $this->product_id)
                ->where('is_active', true)
                ->get();
        }

        if ($prices->isEmpty()) {
            return;
        }

        $recurringPrices = $prices->filter(
            fn (ProductPrice $price) => $price->billing_kind === ProductPriceBillingKind::Recurring,
        );

        $intervalUnit = IntervalUnit::tryFrom($this->interval_unit) ?? IntervalUnit::Month;
        $intervalCount = max(1, (int) $this->interval_count);

        $recurringForInterval = $recurringPrices->first(
            fn (ProductPrice $price) => $price->interval_unit === $intervalUnit
                && (int) $price->interval_count === $intervalCount,
        );

        if ($recurringOnly) {
            $this->applyRecurringPriceAmounts($recurringForInterval);

            return;
        }

        $recurring = $recurringForInterval ?? $recurringPrices->first(
            fn (ProductPrice $price) => $price->interval_unit === IntervalUnit::Month
                && (int) $price->interval_count === 1,
        ) ?? $recurringPrices->first();

        if ($recurring !== null) {
            $this->interval_unit = $recurring->interval_unit->value;
            $this->interval_count = max(1, (int) $recurring->interval_count);
            $this->applyRecurringPriceAmounts($recurring);
            $this->applyDefaultDueAt();

            return;
        }

        $oneTime = $prices->first(
            fn (ProductPrice $price) => $price->billing_kind === ProductPriceBillingKind::OneTime,
        );

        if ($oneTime !== null) {
            $formatted = number_format($oneTime->amount_minor / 100, 2, '.', '');
            $this->first_payment_amount_input = $formatted;
            $this->recurring_amount_input = $formatted;
            $this->currency = strtoupper($oneTime->currency);
        }
    }

    private function applyRecurringPriceAmounts(?ProductPrice $recurring): void
    {
        if ($recurring === null) {
            $this->recurring_amount_input = '';
            $this->first_payment_amount_input = '';

            return;
        }

        $formatted = number_format($recurring->amount_minor / 100, 2, '.', '');
        $this->recurring_amount_input = $formatted;
        $this->first_payment_amount_input = $formatted;
        $this->currency = strtoupper($recurring->currency);
    }

    private function applyDefaultActivationDates(): void
    {
        if ($this->service !== null) {
            return;
        }

        $this->activated_at = now()->format('Y-m-d');
        $this->applyDefaultDueAt();
    }

    private function applyDefaultDueAt(): void
    {
        if ($this->service !== null) {
            return;
        }

        $from = $this->activated_at !== null && $this->activated_at !== ''
            ? Carbon::parse($this->activated_at)->startOfDay()
            : now()->startOfDay();

        $unit = IntervalUnit::tryFrom($this->interval_unit) ?? IntervalUnit::Month;
        $count = max(1, (int) $this->interval_count);

        $due = match ($unit) {
            IntervalUnit::Month => $from->copy()->addMonths($count),
            IntervalUnit::Year => $from->copy()->addYears($count),
        };

        $this->due_at = $due->format('Y-m-d');
    }

    private function parseAmountMinor(string $input): ?int
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        return (int) round((float) $trimmed * 100);
    }

    private function resolveDomainCostMinorFromSelectedPrice(): ?int
    {
        if (! $this->domain_registrar_tld_price_id) {
            return $this->parseAmountMinor($this->domain_cost_input);
        }

        $price = RegistrarTldPrice::query()->find($this->domain_registrar_tld_price_id);
        if ($price?->domain_cost_minor !== null) {
            return (int) $price->domain_cost_minor;
        }

        return $this->parseAmountMinor($this->domain_cost_input);
    }

    private function applyDomainBillingIntervalFromRegistrar(?RegistrarTldPrice $price = null): void
    {
        $tldId = (int) $this->domain_registrar_tld_id;
        if ($tldId <= 0) {
            return;
        }

        $tld = RegistrarTld::query()->find($tldId);
        if ($price === null && $this->domain_registrar_tld_price_id) {
            $price = RegistrarTldPrice::query()->find($this->domain_registrar_tld_price_id);
        }

        [$unit, $count] = Domain::billingIntervalFromRegistrarTld($tld, $price);
        $this->domain_interval_unit = $unit->value;
        $this->domain_interval_count = $count;
    }

    public function sendEmail(BillingEmailSender $sender): void
    {
        if ($this->service === null) {
            return;
        }

        $this->validate([
            'email_template_slug' => [
                'required',
                'string',
                Rule::in($this->availableEmailTemplateSlugs()),
            ],
        ]);

        $service = $this->service->loadMissing(['client', 'product', 'server', 'domain']);

        $clientEmail = $service->client?->email;
        if ($clientEmail === null || $clientEmail === '') {
            Flux::toast(variant: 'danger', text: __('This client has no email address.'));

            return;
        }

        $template = EmailTemplate::query()
            ->where('slug', $this->email_template_slug)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            Flux::toast(variant: 'danger', text: __('Email template not found or inactive.'));

            return;
        }

        $sender->sendServiceEmail(
            $template,
            $clientEmail,
            $service->welcomeEmailVariables(),
            $service->client_id,
            $service->id,
        );

        Flux::toast(variant: 'success', text: __('Email queued to :email.', ['email' => $clientEmail]));
    }

    /**
     * @return list<string>
     */
    private function availableEmailTemplateSlugs(): array
    {
        return BillingEmailSender::SERVICE_TEMPLATE_SLUGS;
    }

    public function render()
    {
        $title = $this->service ? __('Edit service') : __('New service');

        $tldOptions = $this->domain_registrar_id
            ? RegistrarTld::query()->where('registrar_id', $this->domain_registrar_id)->orderBy('tld')->get()
            : collect();

        $domainTldPrices = $this->domain_registrar_tld_id
            ? RegistrarTldPrice::query()
                ->where('registrar_tld_id', $this->domain_registrar_tld_id)
                ->where('operation', $this->domain_registrar_price_operation)
                ->where('is_active', true)
                ->orderBy('term_years')
                ->orderBy('currency')
                ->get()
            : collect();

        $moveClientResults = collect();
        if ($this->service && $this->show_move_service) {
            $search = trim($this->move_client_search);
            if (strlen($search) >= 2) {
                $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $moveClientResults = Client::query()
                    ->whereKeyNot($this->service->client_id)
                    ->where(function ($q) use ($needle) {
                        $q->where('name', 'like', $needle)
                            ->orWhere('email', 'like', $needle)
                            ->orWhere('company_name', 'like', $needle);
                    })
                    ->orderBy('name')
                    ->limit(10)
                    ->get(['id', 'name', 'email', 'company_name']);
            }
        }

        return view('livewire.billing.services.manage', [
            'title' => $title,
            'clients' => $this->service === null
                ? Client::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'moveClientResults' => $moveClientResults,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get([
                'id', 'name', 'default_allocated_ram_mb', 'default_allocated_disk_gb',
            ]),
            'servers' => Server::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ServiceStatus::cases(),
            'intervalUnits' => IntervalUnit::cases(),
            'registrars' => Registrar::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tldOptions' => $tldOptions,
            'registrarTldPriceOperations' => RegistrarTldPriceOperation::cases(),
            'domainTldPrices' => $domainTldPrices,
            'domainStatuses' => DomainStatus::cases(),
            'recipientEmail' => $this->service?->client?->email,
            'emailTemplates' => $this->service
                ? EmailTemplate::query()
                    ->whereIn('slug', $this->availableEmailTemplateSlugs())
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                : collect(),
        ])->title($title);
    }
}
