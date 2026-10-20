<?php

namespace App\Http\Requests;

use App\Support\OperationsDefinition;
use App\Support\WorkforceDefinition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OperationsRequest extends ProductionRequest
{
    public function authorize(): bool
    {
        $r = $this->route('operation_record');
        $a = $this->route()->defaults['operation'] ?? null;
        $m = $this->module();

        return $a ? Gate::allows('operations-action', [$m, $r, $a]) : ($this->isMethod('GET') ? Gate::allows('operations-view', [$m, $r]) : ($r ? Gate::allows('operations-action', [$m, $r, 'update']) : Gate::allows('operations-create', $m)));
    }

    public function rules(): array
    {
        if (OperationsDefinition::production($this->module())) {
            return parent::rules();
        }
        $r = array_fill_keys(['organization_id', 'operational_unit_id', 'status', 'approved_by', 'approved_at', 'requested_by', 'reviewed_by', 'verified_by', 'verified_on', 'prepared_by', 'published_at', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'geofence_valid', 'ranking_score', 'score_breakdown', 'source'], ['missing']);
        if ($this->isMethod('GET')) {
            unset($r['status']);

            return $r + ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(OperationsDefinition::statuses($this->module()))], 'unit_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])]];
        }
        if ($this->route()->defaults['operation'] ?? null) {
            return $r;
        }
        foreach (WorkforceDefinition::fields($this->module()) as $key => [$label,$type,$required]) {
            $r[$key] = match ($type) {
                'select' => [$required ? 'required' : 'nullable', 'integer', 'min:1'],'number','number-zero' => ['required', 'integer', $type === 'number' ? 'min:1' : 'min:0', 'max:1440'],'time' => ['required', 'date_format:H:i'],'date' => [$required ? 'required' : 'nullable', 'date_format:Y-m-d'],'datetime-local' => [$required ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'],default => [$required ? 'required' : 'nullable', 'string', 'max:'.($type === 'textarea' ? 3000 : match ($key) {
                    'code' => 50,'certificate_no' => 100,default => 255
                })]
            };
        }
        foreach (['period_end' => 'period_start', 'ends_at' => 'starts_at', 'clock_out_at' => 'clock_in_at', 'requested_clock_out_at' => 'requested_clock_in_at', 'expires_on' => 'issued_on'] as $end => $start) {
            if (isset($r[$end]) && $this->module() !== 'shift-templates') {
                $r[$end][] = $end === 'period_end' || $end === 'expires_on' ? 'after_or_equal:'.$start : 'after:'.$start;
            }
        }
        if (isset($r['level'])) {
            $r['level'][] = 'max:5';
        }if (isset($r['issued_on'])) {
            $r['issued_on'][] = 'before_or_equal:today';
        }
        if (isset($r['leave_type'])) {
            $r['leave_type'] = ['required', Rule::in(['ANNUAL', 'SICK', 'PERSONAL', 'UNPAID'])];
        }

        return $r;
    }
}
