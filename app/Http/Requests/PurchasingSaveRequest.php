<?php

namespace App\Http\Requests;

use App\Support\PurchasingDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PurchasingSaveRequest extends FormRequest
{
    public function module(): string
    {
        return $this->route()->defaults['module'];
    }

    public function authorize(): bool
    {
        $record = $this->route('purchasing_record');

        return $record ? Gate::allows('purchasing-action', [$this->module(), $record, 'update']) : Gate::allows('purchasing-create', $this->module());
    }

    public static function decimal(bool $positive = false, int $scale = 4): array
    {
        return ['required', 'numeric', $positive ? 'gt:0' : 'min:0', 'regex:/\A\d{1,'.(18 - $scale).'}(?:\.\d{1,'.$scale.'})?\z/'];
    }

    public function rules(): array
    {
        $rules = [];
        foreach (['organization_id', 'operational_unit_id', 'number', 'status', 'subtotal', 'total_amount', 'approved_by', 'approved_at', 'created_by', 'requested_by', 'recorded_by', 'received_by', 'qc_by', 'deleted_at', 'generation_method', 'sourceable_id', 'sourceable_type'] as $key) {
            $rules[$key] = ['missing'];
        }
        foreach (PurchasingDefinition::fields($this->module()) as $key => [$label, $type, $required]) {
            $rule = [$required ? 'required' : 'nullable'];
            $rule = match ($type) {
                'select' => [...$rule, 'integer', 'min:1'], 'date' => [...$rule, 'date_format:Y-m-d'],
                'datetime-local' => [...$rule, 'date_format:Y-m-d\TH:i'],
                'decimal' => self::decimal(false, in_array($key, ['tax_amount', 'discount_amount'], true) ? 2 : 4),
                'number' => [...$rule, 'integer', 'between:0,4294967295'],
                default => [...$rule, 'string', 'max:'.($type === 'textarea' ? 10000 : ($key === 'supplier_sku' ? 80 : 100))],
            };
            if ($key === 'currency') {
                $rule = ['required', 'string', 'regex:/\A[A-Z]{3}\z/'];
            }
            $rules[$key] = $rule;
        }
        foreach (['ends_on' => 'starts_on', 'effective_until' => 'effective_from', 'expected_at' => 'order_date'] as $end => $start) {
            if (isset($rules[$end])) {
                $rules[$end][] = 'after_or_equal:'.$start;
            }
        }
        $fields = PurchasingDefinition::lineFields($this->module());
        if ($fields) {
            $rules['lines'] = ['required', 'array', 'min:1', 'max:100'];
            $rules['lines.*'] = ['required', 'array:'.implode(',', array_keys($fields))];
            foreach ($fields as $key => [$label, $type]) {
                $rule = match ($type) {
                    'select' => ['required', 'integer', 'min:1', 'distinct'],
                    'decimal' => self::decimal(in_array($key, ['required_quantity', 'ordered_quantity', 'received_quantity'], true)),
                    'date' => ['nullable', 'date_format:Y-m-d'],
                    default => ['nullable', 'string', 'max:'.($key === 'rejection_reason' ? 1000 : 100)],
                };
                $rules['lines.*.'.$key] = $rule;
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attrs = [];
        foreach (PurchasingDefinition::fields($this->module()) as $key => $field) {
            $attrs[$key] = $field[0];
        }
        foreach (PurchasingDefinition::lineFields($this->module()) as $key => $field) {
            $attrs['lines.*.'.$key] = $field[0];
        }

        return $attrs;
    }

    public function messages(): array
    {
        return ['required' => ':attribute wajib diisi.', 'missing' => ':attribute ditentukan melalui aksi sistem.', 'array' => 'Struktur :attribute tidak valid.',
            'distinct' => 'Item/baris yang sama tidak boleh diulang.', 'date_format' => 'Format :attribute tidak valid.',
            'after_or_equal' => ':attribute tidak boleh sebelum :date.', 'regex' => 'Format/ketelitian :attribute tidak valid.',
            'max' => ':attribute melebihi batas :max.', 'gt' => ':attribute harus lebih besar dari nol.', 'min' => ':attribute di bawah batas minimum.'];
    }
}
