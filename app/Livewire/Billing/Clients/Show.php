<?php

namespace App\Livewire\Billing\Clients;

use App\Enums\ContactRole;
use App\Enums\ServiceStatus;
use App\Enums\TransactionStatus;
use App\Models\Client;
use App\Models\ClientEmailLog;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $clientId;

    public Client $client;

    public string $tab = 'overview';

    public string $contact_name = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $contact_role = 'owner';

    public bool $contact_is_primary = false;

    public bool $showEmailLogModal = false;

    public ?int $viewingEmailLogId = null;

    public function mount(Client $client): void
    {
        $this->client = $client->load([
            'contacts',
            'services' => fn ($q) => $q->with(['product', 'domain', 'server']),
            'domains',
            'orders' => fn ($q) => $q->orderByDesc('id'),
            'emailLogs',
        ]);
        $this->clientId = $client->id;
    }

    public function viewEmailLog(int $emailLogId): void
    {
        $exists = ClientEmailLog::query()
            ->where('client_id', $this->client->id)
            ->whereKey($emailLogId)
            ->exists();

        if ($exists) {
            $this->viewingEmailLogId = $emailLogId;
            $this->showEmailLogModal = true;
        }
    }

    public function closeEmailLog(): void
    {
        $this->viewingEmailLogId = null;
        $this->showEmailLogModal = false;
    }

    public function addContact(): void
    {
        $data = $this->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'contact_role' => ['required', 'string', 'in:'.implode(',', array_column(ContactRole::cases(), 'value'))],
            'contact_is_primary' => ['boolean'],
        ]);
        if ($this->contact_is_primary) {
            Contact::query()->where('client_id', $this->client->id)->update(['is_primary' => false]);
        }
        Contact::query()->create([
            'client_id' => $this->client->id,
            'name' => $data['contact_name'],
            'email' => $data['contact_email'],
            'phone' => $data['contact_phone'] ?: null,
            'role' => ContactRole::from($data['contact_role']),
            'is_primary' => $data['contact_is_primary'],
        ]);
        $this->reset(['contact_name', 'contact_email', 'contact_phone', 'contact_role', 'contact_is_primary']);
        $this->contact_role = ContactRole::Owner->value;
        $this->client->load('contacts');
        Flux::toast(variant: 'success', text: __('Contact added.'));
    }

    public function deleteContact(int $contactId): void
    {
        $contact = Contact::query()->where('client_id', $this->client->id)->whereKey($contactId)->firstOrFail();
        $contact->delete();
        $this->client->load('contacts');
        Flux::toast(variant: 'success', text: __('Contact removed.'));
    }

    public function setPrimaryContact(int $contactId): void
    {
        Contact::query()->where('client_id', $this->client->id)->update(['is_primary' => false]);
        Contact::query()->where('client_id', $this->client->id)->whereKey($contactId)->update(['is_primary' => true]);
        $this->client->load('contacts');
        Flux::toast(variant: 'success', text: __('Primary contact updated.'));
    }

    public function render()
    {
        $title = $this->client->name;

        $viewingEmailLog = $this->viewingEmailLogId
            ? $this->client->emailLogs->firstWhere('id', $this->viewingEmailLogId)
            : null;

        return view('livewire.billing.clients.show', [
            'roles' => ContactRole::cases(),
            'overview' => $this->buildOverviewStats(),
            'viewingEmailLog' => $viewingEmailLog,
        ])->title($title);
    }

    /**
     * @return array{
     *   products_total: int,
     *   products_active: int,
     *   services_total: int,
     *   services_active: int,
     *   domains_total: int,
     *   paid_totals: list<array{currency: string, minor: int}>,
     *   paid_by_month: list<array{period: string, label: string, currency: string, minor: int}>,
     *   paid_by_year: list<array{year: string, currency: string, minor: int}>,
     * }
     */
    private function buildOverviewStats(): array
    {
        $clientId = $this->client->id;

        $productRow = DB::table('services')
            ->join('products', 'products.id', '=', 'services.product_id')
            ->where('services.client_id', $clientId)
            ->selectRaw('count(distinct services.product_id) as total')
            ->selectRaw('count(distinct case when products.is_active then services.product_id end) as active')
            ->first();

        $productsTotal = $productRow ? (int) $productRow->total : 0;
        $productsActive = $productRow ? (int) $productRow->active : 0;

        $servicesTotal = Service::query()->where('client_id', $clientId)->count();
        $servicesActive = Service::query()
            ->where('client_id', $clientId)
            ->where('status', ServiceStatus::Active)
            ->count();

        $domainsTotal = Domain::query()->where('client_id', $clientId)->count();

        $paymentRows = DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->where('orders.client_id', $clientId)
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->whereNotNull('transactions.paid_at')
            ->orderBy('transactions.paid_at')
            ->get(['transactions.currency', 'transactions.amount_minor', 'transactions.paid_at']);

        $totalsByCurrency = [];

        foreach ($paymentRows as $row) {
            $currency = $row->currency;
            $minor = (int) $row->amount_minor;
            $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0) + $minor;
        }

        $paidTotals = collect($totalsByCurrency)
            ->map(fn (int $minor, string $currency) => ['currency' => $currency, 'minor' => $minor])
            ->sortBy('currency')
            ->values()
            ->all();

        $paidByMonth = $this->buildRecurringByMonthFromActiveServices($clientId);
        $paidByYear = $this->buildRecurringByYearFromActiveServices($clientId);

        return [
            'products_total' => $productsTotal,
            'products_active' => $productsActive,
            'services_total' => $servicesTotal,
            'services_active' => $servicesActive,
            'domains_total' => $domainsTotal,
            'paid_totals' => $paidTotals,
            'paid_by_month' => $paidByMonth,
            'paid_by_year' => $paidByYear,
        ];
    }

    /**
     * Monthly recurring total from active services (sum of stored recurring amounts).
     *
     * @return list<array{period: string, label: string, currency: string, minor: int}>
     */
    private function buildRecurringByMonthFromActiveServices(int $clientId): array
    {
        $byCurrency = $this->recurringMonthlyTotalsByCurrency($clientId);

        $period = now()->format('Y-m');
        $label = Carbon::createFromFormat('Y-m', $period)->format('M Y');

        return collect($byCurrency)
            ->map(fn (int $minor, string $currency) => [
                'period' => $period,
                'label' => $label,
                'currency' => $currency,
                'minor' => $minor,
            ])
            ->sortBy('currency')
            ->values()
            ->all();
    }

    /**
     * @return list<array{year: string, currency: string, minor: int}>
     */
    private function buildRecurringByYearFromActiveServices(int $clientId): array
    {
        $byCurrency = $this->recurringYearlyTotalsByCurrency($clientId);

        $year = now()->format('Y');

        return collect($byCurrency)
            ->map(fn (int $minor, string $currency) => [
                'year' => $year,
                'currency' => $currency,
                'minor' => $minor,
            ])
            ->sortBy('currency')
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function recurringMonthlyTotalsByCurrency(int $clientId): array
    {
        $byCurrency = [];

        Service::query()
            ->where('client_id', $clientId)
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('recurring_amount_minor')
            ->get()
            ->each(function (Service $service) use (&$byCurrency) {
                $minor = $service->monthlyAmountMinor();
                if ($minor <= 0) {
                    return;
                }

                $currency = $service->currency;
                $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + $minor;
            });

        return $byCurrency;
    }

    private function recurringYearlyTotalsByCurrency(int $clientId): array
    {
        $byCurrency = [];

        Service::query()
            ->where('client_id', $clientId)
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('recurring_amount_minor')
            ->get()
            ->each(function (Service $service) use (&$byCurrency) {
                $minor = $service->yearlyAmountMinor();
                if ($minor <= 0) {
                    return;
                }

                $currency = $service->currency;
                $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + $minor;
            });

        return $byCurrency;
    }
}
