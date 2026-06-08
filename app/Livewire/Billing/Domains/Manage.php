<?php

namespace App\Livewire\Billing\Domains;

use App\Enums\DomainStatus;
use App\Enums\IntervalUnit;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public ?Domain $domain = null;

    public ?int $client_id = null;

    public ?int $registrar_id = null;

    public ?int $registrar_tld_id = null;

    public string $fqdn = '';

    public string $status = 'active';

    public string $currency = 'EUR';

    public string $first_payment_amount_input = '';

    public string $recurring_amount_input = '';

    public string $interval_unit = 'year';

    public int $interval_count = 1;

    public string $registered_at = '';

    public string $expires_at = '';

    public string $due_at = '';

    public bool $auto_renew = false;

    public string $notes = '';

    public bool $show_move_domain = false;

    public string $move_client_search = '';

    public ?int $move_target_client_id = null;

    public function mount(?Domain $domain = null): void
    {
        $this->domain = $domain?->load('client');
        if ($domain) {
            $this->client_id = $domain->client_id;
            $this->registrar_id = $domain->registrar_id;
            $this->registrar_tld_id = $domain->registrar_tld_id;
            $this->fqdn = $domain->fqdn;
            $this->status = $domain->status->value;
            $this->currency = $domain->currency;
            $this->first_payment_amount_input = $domain->first_payment_amount_minor !== null
                ? number_format($domain->first_payment_amount_minor / 100, 2, '.', '')
                : '';
            $this->recurring_amount_input = $domain->recurring_amount_minor !== null
                ? number_format($domain->recurring_amount_minor / 100, 2, '.', '')
                : '';
            $this->interval_unit = $domain->interval_unit->value;
            $this->interval_count = max(1, (int) $domain->interval_count);
            $this->registered_at = $domain->registered_at?->format('Y-m-d') ?? '';
            $this->expires_at = $domain->expires_at?->format('Y-m-d') ?? '';
            $this->due_at = $domain->due_at?->format('Y-m-d') ?? '';
            $this->auto_renew = $domain->auto_renew;
            $this->notes = (string) ($domain->notes ?? '');
        } else {
            $cid = request()->integer('client_id');
            $this->client_id = $cid > 0 ? $cid : null;
            if ($this->client_id) {
                $this->applyClientDefaults();
            }
            $this->registered_at = now()->format('Y-m-d');
            $this->expires_at = now()->addYear()->format('Y-m-d');
            $this->due_at = now()->addYear()->format('Y-m-d');
        }
    }

    public function updatedClientId(mixed $value): void
    {
        if ($this->domain !== null) {
            return;
        }
        $cid = (int) $value;
        if ($cid <= 0) {
            return;
        }
        $this->applyClientDefaults();
    }

    public function updatedRegistrarId(?int $value): void
    {
        $this->registrar_tld_id = null;
    }

    public function updatedRegistrarTldId(mixed $value): void
    {
        $tldId = (int) $value;
        if ($tldId <= 0) {
            return;
        }

        $this->applyBillingIntervalFromRegistrarTld($tldId);
    }

    public function updatedShowMoveDomain(bool $value): void
    {
        if (! $value) {
            $this->move_client_search = '';
            $this->move_target_client_id = null;
        }
    }

    public function selectMoveTargetClient(int $clientId): void
    {
        if ($this->domain === null || $clientId === $this->domain->client_id) {
            return;
        }

        $this->move_target_client_id = $clientId;
    }

    public function moveDomain(): void
    {
        if ($this->domain === null) {
            return;
        }

        $this->validate([
            'move_target_client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id'),
                Rule::notIn([$this->domain->client_id]),
            ],
        ]);

        $newClient = Client::query()->findOrFail($this->move_target_client_id);

        $fqdnTaken = Domain::query()
            ->where('client_id', $newClient->id)
            ->where('fqdn', $this->domain->fqdn)
            ->whereKeyNot($this->domain->id)
            ->exists();

        if ($fqdnTaken) {
            $this->addError('move_target_client_id', __('This client already has a domain with the same name.'));

            return;
        }

        DB::transaction(function () use ($newClient) {
            $this->domain->update([
                'client_id' => $newClient->id,
                'currency' => $newClient->currency,
            ]);

            Service::query()
                ->where('domain_id', $this->domain->id)
                ->where('client_id', '!=', $newClient->id)
                ->update(['domain_id' => null]);
        });

        $this->client_id = $newClient->id;
        $this->currency = $newClient->currency;
        $this->domain->load('client');

        $this->show_move_domain = false;
        $this->move_client_search = '';
        $this->move_target_client_id = null;

        Flux::toast(variant: 'success', text: __('Domain moved to :client.', ['client' => $newClient->name]));
    }

    public function save(): void
    {
        $this->client_id = (int) $this->client_id;
        $this->registrar_id = (int) $this->registrar_id;
        $this->registrar_tld_id = $this->registrar_tld_id ? (int) $this->registrar_tld_id : null;

        $amountRule = ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'];

        $fqdnRule = ['required', 'string', 'max:255', 'lowercase'];
        $fqdnRule[] = Rule::unique('domains', 'fqdn')
            ->where('client_id', $this->client_id)
            ->ignore($this->domain?->id);
        $rules = [
            'registrar_id' => ['required', 'exists:registrars,id'],
            'registrar_tld_id' => ['nullable', 'exists:registrar_tlds,id'],
            'fqdn' => $fqdnRule,
            'status' => ['required', Rule::enum(DomainStatus::class)],
            'currency' => ['required', 'string', 'size:3'],
            'first_payment_amount_input' => $amountRule,
            'recurring_amount_input' => $amountRule,
            'interval_unit' => ['required', Rule::enum(IntervalUnit::class)],
            'interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'registered_at' => ['required', 'date'],
            'expires_at' => ['required', 'date'],
            'due_at' => ['required', 'date'],
            'auto_renew' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];

        if ($this->domain === null) {
            $rules['client_id'] = ['required', 'exists:clients,id'];
        }

        $this->validate($rules);

        if ($this->registrar_tld_id) {
            $tld = RegistrarTld::query()->findOrFail($this->registrar_tld_id);
            if ((int) $tld->registrar_id !== (int) $this->registrar_id) {
                $this->addError('registrar_tld_id', __('TLD must belong to the selected registrar.'));

                return;
            }
        }

        $payload = [
            'client_id' => $this->client_id,
            'registrar_id' => $this->registrar_id,
            'registrar_tld_id' => $this->registrar_tld_id,
            'fqdn' => strtolower(trim($this->fqdn)),
            'status' => DomainStatus::from($this->status),
            'currency' => strtoupper($this->currency),
            'first_payment_amount_minor' => $this->parseAmountMinor($this->first_payment_amount_input),
            'recurring_amount_minor' => $this->parseAmountMinor($this->recurring_amount_input),
            'interval_unit' => IntervalUnit::from($this->interval_unit),
            'interval_count' => max(1, (int) $this->interval_count),
            'registered_at' => $this->registered_at,
            'expires_at' => $this->expires_at,
            'due_at' => $this->due_at,
            'auto_renew' => $this->auto_renew,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        if ($this->domain) {
            $this->domain->update($payload);
            $this->domain->load('client');
            Flux::toast(variant: 'success', text: __('Domain updated.'));
            $this->redirect(route('billing.domains.edit', $this->domain), navigate: true);

            return;
        }
        $domain = Domain::query()->create($payload);
        Flux::toast(variant: 'success', text: __('Domain created.'));
        $this->redirect(route('billing.domains.edit', $domain), navigate: true);
    }

    private function applyClientDefaults(): void
    {
        $client = Client::query()->find($this->client_id);
        if ($client) {
            $this->currency = $client->currency;
        }
    }

    private function parseAmountMinor(string $input): ?int
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        return (int) round((float) $trimmed * 100);
    }

    private function applyBillingIntervalFromRegistrarTld(int $tldId): void
    {
        $tld = RegistrarTld::query()->find($tldId);
        if ($tld === null) {
            return;
        }

        [$unit, $count] = $tld->defaultBillingInterval();
        $this->interval_unit = $unit->value;
        $this->interval_count = $count;
    }

    public function render()
    {
        $title = $this->domain ? __('Edit domain') : __('New domain');
        $tldOptions = $this->registrar_id
            ? RegistrarTld::query()->where('registrar_id', $this->registrar_id)->orderBy('tld')->get()
            : collect();

        $moveClientResults = collect();
        if ($this->domain && $this->show_move_domain) {
            $search = trim($this->move_client_search);
            if (strlen($search) >= 2) {
                $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $moveClientResults = Client::query()
                    ->whereKeyNot($this->domain->client_id)
                    ->where(function ($q) use ($needle) {
                        $q->where('name', 'like', $needle)
                            ->orWhere('email', 'like', $needle);
                    })
                    ->orderBy('name')
                    ->limit(10)
                    ->get(['id', 'name', 'email', 'company_name']);
            }
        }

        return view('livewire.billing.domains.manage', [
            'title' => $title,
            'whoisUrl' => RegistrarTld::resolveWhoisUrl($this->registrar_id, $this->registrar_tld_id, $this->fqdn),
            'clients' => $this->domain === null
                ? Client::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'moveClientResults' => $moveClientResults,
            'registrars' => Registrar::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tldOptions' => $tldOptions,
            'statuses' => DomainStatus::cases(),
            'intervalUnits' => IntervalUnit::cases(),
        ])->title($title);
    }
}
