<?php

namespace App\Http\Requests;

use App\Support\OperationsDefinition;
use App\Support\ProductionDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionRequest extends FormRequest
{
    public function module(): string
    {
        return $this->route()->defaults['module'];
    }

    public function rules(): array
    {
        $rules = array_fill_keys(['organization_id', 'operational_unit_id', 'production_unit_id', 'brand_id', 'status', 'qc_status', 'approved_by', 'checked_by', 'generated_by', 'created_by', 'output_inventory_batch_id', 'unit_cost', 'completed_quantity', 'batch_no'], ['missing']);
        if ($this->isMethod('GET')) {
            unset($rules['status']);

            return $rules + ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(OperationsDefinition::statuses($this->module()))], 'unit_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])]];
        }
        $a = $this->route()->defaults['operation'] ?? null;
        if ($a) {
            if ($a === 'complete') {
                return $rules + ['actual_quantity' => self::decimal(true), 'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today']];
            }
            if ($a === 'start' && $this->module() === 'production-batches') {
                return $rules + ['usages' => ['required', 'array', 'min:1', 'max:300'], 'usages.*' => ['array:usage_id,actual_quantity'], 'usages.*.usage_id' => ['required', 'integer', 'distinct'], 'usages.*.actual_quantity' => self::decimal(true), 'usage_reason' => ['required', 'string', 'max:2000']];
            }

            return $rules;
        }
        foreach (ProductionDefinition::fields($this->module()) as $key => $field) {
            $rules[$key] = $this->field($key, $field);
        }
        $lines = ProductionDefinition::lineFields($this->module());
        if ($lines) {
            $rules['lines'] = ['required', 'array', 'min:1', 'max:100'];
            $rules['lines.*'] = ['array:'.implode(',', array_keys($lines))];
            foreach ($lines as $key => $field) {
                $rules['lines.*.'.$key] = $this->field($key, $field);
            }
        }
        foreach (['ends_at' => 'starts_at', 'planned_end_at' => 'planned_start_at'] as $end => $start) {
            if (isset($rules[$end])) {
                $rules[$end][] = 'after:'.$start;
            }
        }

        return $rules;
    }

    public static function decimal(bool $positive = false, bool $nullable = false): array
    {
        return [$nullable ? 'nullable' : 'required', 'numeric', $positive ? 'gt:0' : 'min:0', 'regex:/\A\d{1,14}(?:\.\d{1,4})?\z/'];
    }

    private function field(string $key, array $f): array
    {
        [$label,$type,$required] = $f;
        $rules = match ($type) {
            'select' => [$required ? 'required' : 'nullable', 'integer', 'min:1'],
            'decimal','optional-decimal' => self::decimal(in_array($key, ['planned_quantity', 'target_quantity'], true), ! $required),
            'signed-decimal' => ['nullable', 'numeric', 'regex:/\A-?\d{1,14}(?:\.\d{1,4})?\z/'],
            'number' => ['required', 'integer', 'between:1,1000'],
            'date' => [$required ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'datetime-local' => [$required ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'],
            default => [$required ? 'required' : 'nullable', 'string', 'max:'.($type === 'textarea' ? 3000 : ($key === 'unit' ? 30 : 255))]
        };
        if ($key === 'safety_percent') {
            $rules[] = 'max:1000';
        }
        if ($key === 'task_type') {
            $rules = ['required', Rule::in($this->module() === 'packing-dispatch' ? ['PACKING', 'DISPATCH'] : ['PREPARATION', 'COOKING', 'CLEANING', 'PACKING', 'DISPATCH'])];
        }
        if ($key === 'check_type') {
            $rules = ['required', Rule::in(['FINAL', 'TEMPERATURE', 'WEIGHT', 'VISUAL'])];
        }
        if ($key === 'result') {
            $rules = ['required', Rule::in(['PASS', 'FAIL'])];
        }

        return $rules;
    }
}
