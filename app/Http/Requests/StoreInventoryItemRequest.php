<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public const TYPES = ['RAW_MATERIAL' => 'Bahan baku', 'SEMI_FINISHED' => 'Bahan setengah jadi', 'FINISHED_GOOD' => 'Barang jadi', 'PACKAGING' => 'Kemasan', 'CONSUMABLE' => 'Bahan habis pakai'];

    public const TRACKING = ['NONE' => 'Tanpa batch', 'LOT' => 'Batch / lot', 'LOT_EXPIRY' => 'Batch dan kedaluwarsa'];

    public const ISSUE = ['FIFO' => 'FIFO — masuk lebih dahulu', 'FEFO' => 'FEFO — kedaluwarsa terdekat'];

    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper(trim($this->input('sku')))]);
        }
    }

    public function rules(): array
    {
        $item = $this->route('inventory_item');
        $currentBrand = $item instanceof InventoryItem ? $item->brand_id : null;

        return [
            'brand_id' => ['nullable', 'integer', 'min:1', Rule::exists('brands', 'id')->where('organization_id', $this->user()->organization_id)
                ->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $currentBrand ?? 0))],
            'base_uom_id' => ['required', 'integer', 'min:1', Rule::exists('units_of_measure', 'id')],
            'sku' => ['bail', 'required', 'string', 'max:80', 'regex:/\A[A-Z0-9][A-Z0-9._-]*\z/', function (string $attribute, mixed $value, Closure $fail) use ($item): void {
                if (InventoryItem::withTrashed()->where('organization_id', $this->user()->organization_id)->whereRaw('LOWER(sku) = ?', [strtolower($value)])
                    ->when($item instanceof InventoryItem, fn ($query) => $query->where('id', '!=', $item->id))->exists()) {
                    $fail('SKU sudah digunakan dalam organization ini, termasuk item yang diarsipkan.');
                }
            }],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'tracking_policy' => ['required', Rule::in(array_keys(self::TRACKING))],
            'issue_policy' => ['required', Rule::in(array_keys(self::ISSUE)), function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === 'FEFO' && $this->input('tracking_policy') !== 'LOT_EXPIRY') {
                    $fail('FEFO memerlukan tracking batch dan kedaluwarsa (LOT_EXPIRY).');
                }
            }],
            'reorder_point' => ['required', 'numeric', 'regex:/\A\d{1,14}(?:\.\d{1,4})?\z/'],
            'safety_stock' => ['required', 'numeric', 'regex:/\A\d{1,14}(?:\.\d{1,4})?\z/'],
            'standard_cost' => ['required', 'numeric', 'regex:/\A\d{1,14}(?:\.\d{1,4})?\z/'],
            'is_active' => ['required', 'boolean'],
            'organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'deleted_at' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return ['required' => ':attribute wajib diisi.', 'brand_id.exists' => 'Brand tidak tersedia dalam organization Anda.',
            'base_uom_id.exists' => 'Satuan dasar tidak tersedia.', 'sku.regex' => 'SKU hanya boleh berisi huruf, angka, titik, underscore, dan tanda hubung.',
            'reorder_point.regex' => 'Reorder point harus nonnegatif, maksimal 14 digit bilangan bulat dan 4 desimal.',
            'safety_stock.regex' => 'Safety stock harus nonnegatif, maksimal 14 digit bilangan bulat dan 4 desimal.',
            'standard_cost.regex' => 'Biaya standar harus nonnegatif, maksimal 14 digit bilangan bulat dan 4 desimal.',
            'in' => 'Pilihan :attribute tidak valid.', 'missing' => ':attribute ditentukan oleh sistem.'];
    }

    public function attributes(): array
    {
        return ['base_uom_id' => 'Satuan dasar', 'name' => 'Nama item', 'item_type' => 'Tipe item', 'is_active' => 'Status',
            'tracking_policy' => 'Tracking', 'issue_policy' => 'Kebijakan pengeluaran', 'standard_cost' => 'Biaya standar'];
    }
}
