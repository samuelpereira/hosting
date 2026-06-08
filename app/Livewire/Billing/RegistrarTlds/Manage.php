<?php

namespace App\Livewire\Billing\RegistrarTlds;

use App\Enums\RegistrarTldPriceOperation;
use App\Models\Registrar;
use App\Models\RegistrarTld;
use App\Models\RegistrarTldPrice;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public ?RegistrarTld $registrarTld = null;

    public ?int $registrar_id = null;

    public string $tld = '';

    public string $whois_link_template = '';

    /** @var array<int, array{id: ?int, operation: string, term_years: int, currency: string, amount_input: string, domain_cost_input: string, is_active: bool}> */
    public array $priceRows = [];

    public function mount(?RegistrarTld $registrarTld = null): void
    {
        $this->registrarTld = $registrarTld;
        if ($registrarTld) {
            $this->registrar_id = $registrarTld->registrar_id;
            $this->tld = ltrim($registrarTld->tld, '.');
            $this->whois_link_template = (string) ($registrarTld->whois_link_template ?? '');
            $this->priceRows = $registrarTld->prices()->orderBy('id')->get()->map(fn (RegistrarTldPrice $p) => [
                'id' => $p->id,
                'operation' => $p->operation->value,
                'term_years' => (int) $p->term_years,
                'currency' => $p->currency,
                'amount_input' => number_format($p->amount_minor / 100, 2, '.', ''),
                'domain_cost_input' => $p->domain_cost_minor !== null
                    ? number_format($p->domain_cost_minor / 100, 2, '.', '')
                    : '',
                'is_active' => $p->is_active,
            ])->all();
        }
        if ($this->priceRows === []) {
            $this->addPriceRow();
        }
    }

    public function addPriceRow(): void
    {
        $this->priceRows[] = [
            'id' => null,
            'operation' => RegistrarTldPriceOperation::Register->value,
            'term_years' => 1,
            'currency' => 'EUR',
            'amount_input' => '0.00',
            'domain_cost_input' => '',
            'is_active' => true,
        ];
    }

    public function removePriceRow(int $index): void
    {
        unset($this->priceRows[$index]);
        $this->priceRows = array_values($this->priceRows);
        if ($this->priceRows === []) {
            $this->addPriceRow();
        }
    }

    public function save(): void
    {
        $this->registrar_id = (int) $this->registrar_id;

        $uniqueRule = Rule::unique('registrar_tlds', 'tld')
            ->where('registrar_id', $this->registrar_id)
            ->ignore($this->registrarTld?->id);

        $this->validate([
            'registrar_id' => ['required', 'exists:registrars,id'],
            'tld' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9-]+$/', $uniqueRule],
            'whois_link_template' => ['nullable', 'string', 'max:512'],
            'priceRows' => ['required', 'array', 'min:1'],
            'priceRows.*.operation' => ['required', Rule::enum(RegistrarTldPriceOperation::class)],
            'priceRows.*.term_years' => ['required', 'integer', 'min:1', 'max:10'],
            'priceRows.*.currency' => ['required', 'string', 'size:3'],
            'priceRows.*.amount_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'priceRows.*.domain_cost_input' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'priceRows.*.is_active' => ['boolean'],
        ]);

        foreach ($this->priceRows as $i => $row) {
            foreach ($this->priceRows as $j => $other) {
                if ($i >= $j) {
                    continue;
                }
                if ($row['operation'] === $other['operation'] && (int) $row['term_years'] === (int) $other['term_years']) {
                    $this->addError('priceRows.'.$i.'.operation', __('Duplicate operation and term for this TLD.'));

                    return;
                }
            }
        }

        $normalized = RegistrarTld::normalizeTld($this->tld);

        $payload = [
            'registrar_id' => $this->registrar_id,
            'tld' => $normalized,
            'whois_link_template' => trim($this->whois_link_template) !== '' ? trim($this->whois_link_template) : null,
        ];

        if ($this->registrarTld) {
            $this->registrarTld->update($payload);
            $tld = $this->registrarTld->fresh();
        } else {
            $tld = RegistrarTld::query()->create($payload);
        }

        $keepIds = [];
        foreach ($this->priceRows as $row) {
            $minor = (int) round((float) $row['amount_input'] * 100);
            $fields = [
                'operation' => RegistrarTldPriceOperation::from($row['operation']),
                'term_years' => (int) $row['term_years'],
                'amount_minor' => $minor,
                'domain_cost_minor' => $this->parseOptionalAmountMinor($row['domain_cost_input'] ?? ''),
                'currency' => strtoupper($row['currency']),
                'is_active' => (bool) $row['is_active'],
            ];
            if (! empty($row['id'])) {
                RegistrarTldPrice::query()
                    ->where('registrar_tld_id', $tld->id)
                    ->whereKey($row['id'])
                    ->update($fields);
                $keepIds[] = (int) $row['id'];
            } else {
                $created = $tld->prices()->create($fields);
                $keepIds[] = $created->id;
            }
        }
        $tld->prices()->whereNotIn('id', $keepIds)->delete();

        Flux::toast(variant: 'success', text: $this->registrarTld ? __('TLD updated.') : __('TLD created.'));
        $this->redirect(route('billing.registrar-tlds.edit', $tld), navigate: true);
    }

    private function parseOptionalAmountMinor(string $input): ?int
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        return (int) round((float) $trimmed * 100);
    }

    public function render()
    {
        $title = $this->registrarTld ? __('Edit TLD') : __('New TLD');

        return view('livewire.billing.registrar-tlds.manage', [
            'title' => $title,
            'registrars' => Registrar::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'operations' => RegistrarTldPriceOperation::cases(),
        ])->title($title);
    }
}
