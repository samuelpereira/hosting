<?php

namespace App\Livewire\Billing\Products;

use App\Enums\IntervalUnit;
use App\Enums\ProductPriceBillingKind;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductPrice;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public ?Product $product = null;

    public string $type = 'vps';

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public ?int $default_allocated_ram_mb = null;

    public ?int $default_allocated_disk_gb = null;

    public bool $is_active = true;

    /** @var array<int, array{id: ?int, billing_kind: string, interval_unit: string, interval_count: int, currency: string, amount_input: string, is_active: bool}> */
    public array $priceRows = [];

    public function mount(?Product $product = null): void
    {
        $this->product = $product;
        if ($product) {
            $data = $product->only([
                'name', 'slug', 'description',
                'default_allocated_ram_mb', 'default_allocated_disk_gb',
                'is_active',
            ]);
            $this->fill(collect($data)->map(function ($value, string $key) {
                if ($key === 'is_active') {
                    return (bool) $value;
                }
                if (in_array($key, ['default_allocated_ram_mb', 'default_allocated_disk_gb'], true)) {
                    return $value === null ? null : (int) $value;
                }

                return $value ?? '';
            })->all());
            $this->type = $product->type->value;
            $this->priceRows = $product->prices()->orderBy('id')->get()->map(fn (ProductPrice $p) => [
                'id' => $p->id,
                'billing_kind' => $p->billing_kind->value,
                'interval_unit' => $p->interval_unit->value,
                'interval_count' => (int) $p->interval_count,
                'currency' => $p->currency,
                'amount_input' => number_format($p->amount_minor / 100, 2, '.', ''),
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
            'billing_kind' => ProductPriceBillingKind::Recurring->value,
            'interval_unit' => IntervalUnit::Month->value,
            'interval_count' => 1,
            'currency' => 'USD',
            'amount_input' => '0.00',
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
        $this->validate([
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($this->product?->id),
            ],
            'description' => ['nullable', 'string'],
            'default_allocated_ram_mb' => ['nullable', 'integer', 'min:0'],
            'default_allocated_disk_gb' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'priceRows' => ['required', 'array', 'min:1'],
            'priceRows.*.billing_kind' => ['required', Rule::enum(ProductPriceBillingKind::class)],
            'priceRows.*.interval_unit' => ['required', Rule::enum(IntervalUnit::class)],
            'priceRows.*.interval_count' => ['required', 'integer', 'min:1'],
            'priceRows.*.currency' => ['required', 'string', 'size:3'],
            'priceRows.*.amount_input' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'priceRows.*.is_active' => ['boolean'],
        ]);

        $payload = [
            'type' => ProductType::from($this->type),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description !== '' ? $this->description : null,
            'default_allocated_ram_mb' => $this->nullableNonNegativeInt($this->default_allocated_ram_mb),
            'default_allocated_disk_gb' => $this->nullableNonNegativeInt($this->default_allocated_disk_gb),
            'is_active' => $this->is_active,
        ];
        if (! $this->product) {
            $product = Product::query()->create($payload);
        } else {
            $this->product->update($payload);
            $product = $this->product->fresh();
        }

        $keepIds = [];
        foreach ($this->priceRows as $row) {
            $minor = (int) round((float) $row['amount_input'] * 100);
            $fields = [
                'billing_kind' => ProductPriceBillingKind::from($row['billing_kind']),
                'interval_unit' => IntervalUnit::from($row['interval_unit']),
                'interval_count' => (int) $row['interval_count'],
                'amount_minor' => $minor,
                'currency' => strtoupper($row['currency']),
                'is_active' => (bool) $row['is_active'],
            ];
            if (! empty($row['id'])) {
                ProductPrice::query()->where('product_id', $product->id)->whereKey($row['id'])->update($fields);
                $keepIds[] = (int) $row['id'];
            } else {
                $created = $product->prices()->create($fields);
                $keepIds[] = $created->id;
            }
        }
        $product->prices()->whereNotIn('id', $keepIds)->delete();

        Flux::toast(variant: 'success', text: __('Product saved.'));
        $this->redirect(route('billing.products.show', $product), navigate: true);
    }

    private function nullableNonNegativeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    public function render()
    {
        $title = $this->product ? __('Edit product') : __('New product');

        return view('livewire.billing.products.manage', [
            'title' => $title,
            'productTypes' => ProductType::cases(),
            'billingKinds' => ProductPriceBillingKind::cases(),
            'intervalUnits' => IntervalUnit::cases(),
        ])->title($title);
    }
}
