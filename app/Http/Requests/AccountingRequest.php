<?php

namespace App\Http\Requests;

use App\Support\AccountingDefinition as Definition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AccountingRequest extends FormRequest
{
    public function module(): string
    {
        return $this->route()->defaults['module'];
    }

    public function authorize(): bool
    {
        $m = $this->module();
        $r = $this->route('accounting_record');
        $a = $this->route()->defaults['operation'] ?? null;

        return $a ? Gate::allows('accounting-action', [$m, $r, $a]) : ($this->isMethod('GET') ? Gate::allows('accounting-view', [$m, $r]) : ($r ? Gate::allows('accounting-action', [$m, $r, 'update']) : Gate::allows('accounting-create', $m)));
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(Definition::statuses($this->module()))], 'unit_id' => ['nullable', 'integer', 'min:1'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])], 'page' => ['nullable', 'integer', 'min:1']];
        }
        $r = array_fill_keys(['organization_id', 'operational_unit_id', 'status', 'approved_by', 'approved_at', 'submitted_by', 'created_by', 'performed_by', 'awarded_by', 'awarded_at', 'paid_at', 'paid_on', 'locked_by', 'locked_at', 'targetable_type', 'targetable_id', 'measurable_type', 'measurable_id', 'gross_total', 'net_total', 'deduction_total', 'score', 'total_score', 'score_breakdown', 'calculation_detail', 'balance_after', 'number', 'bill_id', 'source_type', 'source_id', 'base_salary', 'net_pay', 'gross_pay', 'expected_amount_server'], ['missing']);
        $a = $this->route()->defaults['operation'] ?? null;
        if ($a) {
            if ($a === 'pay') {
                return $r + ['payment_method' => ['required', Rule::in(array_keys(Definition::options()['payment_method']))], 'payment_reference' => ['required', 'string', 'max:120']];
            }
            if ($a === 'generate') {
                return $r + ['billing_month' => ['required', 'date_format:Y-m']];
            }

            return $r;
        }
        foreach (Definition::fields($this->module()) as $key => [$label,$type,$required]) {
            $r[$key] = [$required ? 'required' : 'nullable', ...match ($type) {
                'money' => ['numeric', 'min:0', 'regex:/\A\d{1,16}(?:\.\d{1,2})?\z/'],
                'decimal' => ['numeric', 'min:0', 'regex:/\A\d{1,14}(?:\.\d{1,4})?\z/'],
                'date' => ['date_format:Y-m-d'],
                'number' => ['integer', 'between:1,28'],
                'select' => isset(Definition::options()[$key]) ? [Rule::in(array_keys(Definition::options()[$key]))] : ['integer', 'min:1'],
                default => ['string', 'max:'.match ($key) {
                    'code' => 50,'bill_no' => 100,'account_or_contract_no' => 120,default => $type === 'textarea' ? 3000 : 255
                }],
            }];
        }
        foreach (['ends_on' => 'starts_on', 'period_end' => 'period_start'] as $end => $start) {
            if (isset($r[$end])) {
                $r[$end][] = 'after_or_equal:'.$start;
            }
        }
        foreach (['amount', 'target_value', 'weight'] as $key) {
            if (isset($r[$key])) {
                $r[$key][] = 'gt:0';
            }
        }
        if (isset($r['weight'])) {
            $r['weight'][] = 'max:100';
        }
        if (isset($r['expense_date'])) {
            $r['expense_date'][] = 'before_or_equal:today';
        }
        if (isset($r['type'])) {
            $r['type'] = ['required', Rule::in($this->module() === 'employee-recognitions' ? ['REWARD', 'RECOGNITION'] : ['RENT', 'UTILITY', 'SERVICE', 'OTHER'])];
        }
        if ($this->module() === 'petty-cash') {
            $r['submission_token'] = ['required', 'uuid'];
        }

        return $r;
    }
}
