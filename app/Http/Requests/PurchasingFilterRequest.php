<?php

namespace App\Http\Requests;

use App\Support\PurchasingAccess;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PurchasingFilterRequest extends FormRequest
{
    public function module(): string
    {
        return $this->route()->defaults['module'];
    }

    public function authorize(): bool
    {
        return Gate::allows('purchasing-view', $this->module());
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:30', 'regex:/\A[A-Z_]+\z/'],
            'page' => ['nullable', 'integer', 'min:1'], 'archived' => ['nullable', Rule::in(['no', 'yes', 'all'])],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'organization_id' => ['missing'], 'operational_unit_id' => ['missing'],
            'destination_unit_id' => ['bail', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! PurchasingAccess::units($this->user(), $this->module())->whereKey($value)->exists()) {
                    $fail('Unit tidak tersedia dalam akses Anda.');
                }
            }],
            'supplier_id' => ['bail', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! PurchasingAccess::suppliers($this->user())->whereKey($value)->exists()) {
                    $fail('Supplier tidak tersedia.');
                }
            }],
            'inventory_item_id' => ['bail', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! PurchasingAccess::items($this->user(), $this->module())->whereKey($value)->exists()) {
                    $fail('Item tidak tersedia.');
                }
            }],
            'purchase_order_id' => ['bail', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! PurchasingAccess::orders($this->user(), $this->module())->whereKey($value)->exists()) {
                    $fail('PO tidak tersedia.');
                }
            }],
        ];
    }
}
