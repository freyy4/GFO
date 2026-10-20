<?php

namespace App\Http\Requests;

use App\Support\TradeDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TradeRequest extends FormRequest
{
    public function module(): string
    {
        return $this->route()->defaults['module'];
    }

    public function authorize(): bool
    {
        $m = $this->module();
        $record = $this->route('trade_record');
        $action = $this->route()->defaults['operation'] ?? null;
        if ($action) {
            return Gate::allows('trade-action', [$m, $record, $action]);
        }
        if ($this->isMethod('GET')) {
            return Gate::allows('trade-view', [$m, $record]);
        }

        return $record ? Gate::allows('trade-action', [$m, $record, 'update']) : Gate::allows('trade-create', $m);
    }

    public function rules(): array
    {
        $rules = array_fill_keys(['organization_id', 'operational_unit_id', 'status', 'approved_by', 'cashier_id', 'performed_by', 'subtotal', 'total_amount', 'unit_price', 'unit_cogs', 'cogs_amount', 'balance_after', 'reserved', 'on_hand'], ['missing']);
        if ($this->isMethod('GET')) {
            return $rules + ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:30'], 'unit_id' => ['nullable', 'integer', 'min:1'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])], 'page' => ['nullable', 'integer', 'min:1']];
        }
        $action = $this->route()->defaults['operation'] ?? null;
        if ($action) {
            return $rules + match ($action) {
                'checkout' => ['payment_method' => ['required', Rule::in(['CASH', 'CARD', 'QRIS', 'TRANSFER'])], 'tendered_amount' => self::decimal(2), 'reference_no' => ['nullable', 'string', 'max:120']],
                'close' => ['closing_cash' => self::decimal(2)],default => []
            };
        }
        if ($this->module() === 'inventory-movements') {
            $rules['submission_token'] = ['required', 'uuid'];
        }
        foreach (TradeDefinition::fields($this->module()) as $key => $field) {
            $rules[$key] = $this->fieldRules($key, $field);
        }
        $lines = TradeDefinition::lineFields($this->module());
        if ($lines) {
            $rules['lines'] = ['required', 'array', 'min:1', 'max:100'];
            $rules['lines.*'] = ['array:'.implode(',', array_keys($lines))];
            foreach ($lines as $key => $field) {
                $rules['lines.*.'.$key] = $this->fieldRules($key, $field);
            }
        }
        foreach (['expires_on' => 'manufactured_on', 'period_end' => 'period_start'] as $end => $start) {
            if (isset($rules[$end]) && $this->filled($start)) {
                $rules[$end][] = 'after_or_equal:'.$start;
            }
        }
        if (isset($rules['birth_date'])) {
            $rules['birth_date'][] = 'before_or_equal:today';
        }

        return $rules;
    }

    public static function decimal(int $scale = 4): array
    {
        return ['required', 'numeric', 'min:0', 'regex:/\A\d{1,'.(18 - $scale).'}(?:\.\d{1,'.$scale.'})?\z/'];
    }

    private function fieldRules(string $key, array $field): array
    {
        [$label,$type,$required] = $field;
        $rules = match ($type) {
            'signed-money' => ['required', 'numeric', 'regex:/\A-?\d{1,16}(?:\.\d{1,2})?\z/'],
            'decimal','money' => self::decimal($type === 'money' ? 2 : 4),
            'select' => [$required ? 'required' : 'nullable', 'integer', 'min:1'],
            'date' => [$required ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'email' => ['nullable', 'email', 'max:255'],
            default => [$required ? 'required' : 'nullable', 'string', 'max:'.($type === 'textarea' ? 3000 : match ($key) {
                'batch_no' => 100,'code','reason_code' => 40,'phone' => 30,'reference_no' => 120,default => 255
            })],
        };
        if (in_array($key, ['quantity', 'requested_quantity'], true)) {
            $rules[] = 'gt:0';
        }
        if ($key === 'direction') {
            $rules = ['required', Rule::in(['IN', 'OUT'])];
        }
        if ($key === 'payment_method') {
            $rules = ['required', Rule::in(['CASH', 'CARD', 'QRIS', 'TRANSFER'])];
        }

        return $rules;
    }
}
