<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperationsRequest;
use App\Models\DemandForecast;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Position;
use App\Models\ProductionSchedule;
use App\Models\ProductionTask;
use App\Models\ProductVariant;
use App\Models\RecipeVersion;
use App\Models\Roster;
use App\Models\ShiftTemplate;
use App\Models\Skill;
use App\Support\OperationsAccess as Access;
use App\Support\OperationsDefinition as Definition;
use App\Support\OperationsMutation;
use App\Support\ProductionAccess;
use App\Support\TradeMutation;
use App\Support\WorkforceAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(OperationsRequest $request): View
    {
        $m = $request->module();
        $filters = $request->validated();
        $q = Access::query($request->user(), $m);
        $column = match ($m) {
            'production-plans' => 'number','production-batches' => 'batch_no','shift-templates','skills' => 'name','production-tasks','packing-dispatch','employee-certifications' => 'name',default => 'id'
        };
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $q->where(function ($q) use ($m, $column, $term) {
                $column === 'id' ? $q->where('id', ctype_digit($term) ? $term : 0) : $q->where($column, 'like', '%'.$term.'%');
                $relation = match ($m) {
                    'shift-assignments', 'leave-requests', 'attendance-records', 'employee-skills', 'employee-certifications', 'replacement-candidates', 'shift-claims' => 'employee',
                    'attendance-corrections' => 'attendanceRecord.employee',
                    'shift-swaps' => 'fromAssignment.employee',
                    default => null,
                };
                if ($relation) {
                    $q->orWhereHas($relation, fn ($employee) => $employee->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_no', 'like', '%'.$term.'%'));
                }
            });
        }
        if (! empty($filters['status'])) {
            if ($m === 'production-material-requirements') {
                $q->where('procurement_status', $filters['status']);
            } elseif ($m === 'employee-skills') {
                $filters['status'] === 'VERIFIED' ? $q->whereNotNull('verified_by') : $q->whereNull('verified_by');
            } elseif ($m === 'production-qc') {
                $q->where('result', $filters['status']);
            } elseif ($m === 'shift-templates') {
                $q->where('is_active', $filters['status'] === 'ACTIVE');
            } elseif (! in_array($m, ['skills', 'employee-skills', 'production-material-requirements'], true)) {
                $q->where('status', $filters['status']);
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (! empty($filters[$key])) {
                $q->whereDate('created_at', $op, $filters[$key]);
            }
        }
        if (! empty($filters['unit_id'])) {
            abort_unless(Access::units($request->user(), $m)->whereKey($filters['unit_id'])->exists(), 403);
            $this->filterUnit($q, $m, (int) $filters['unit_id']);
        }
        $records = $q->orderByDesc('id')->paginate(15)->withQueryString();

        return view('operations.index', $this->data($request, $m) + compact('records', 'filters'));
    }

    private function filterUnit($q, string $m, int $id): void
    {
        match ($m) {
            'demand-forecasts','rosters','shift-assignments','attendance-records' => $q->where('operational_unit_id', $id),
            'production-plans','production-batches' => $q->where('production_unit_id', $id),
            'production-material-requirements','production-schedules' => $q->whereHas('productionPlan', fn ($q) => $q->where('production_unit_id', $id)),
            'production-tasks','packing-dispatch' => $q->whereHas('productionSchedule.productionPlan', fn ($q) => $q->where('production_unit_id', $id)),
            'production-qc' => $q->whereHas('productionBatch', fn ($q) => $q->where('production_unit_id', $id)),
            'leave-requests','employee-skills','employee-certifications' => $q->whereHas('employee', fn ($q) => $q->where('home_unit_id', $id)),
            'attendance-corrections' => $q->whereHas('attendanceRecord', fn ($q) => $q->where('operational_unit_id', $id)),
            'open-shifts' => $q->whereHas('shiftAssignment', fn ($q) => $q->where('operational_unit_id', $id)),
            'replacement-candidates','shift-claims' => $q->whereHas('openShift.shiftAssignment', fn ($q) => $q->where('operational_unit_id', $id)),
            'shift-swaps' => $q->whereHas('fromAssignment', fn ($q) => $q->where('operational_unit_id', $id)),default => $q
        };
    }

    public function create(OperationsRequest $request): View
    {
        $m = $request->module();
        Gate::authorize('operations-create', $m);
        $class = Definition::models()[$m];

        return view('operations.create', $this->data($request, $m) + ['record' => new $class, 'lines' => []]);
    }

    public function show(OperationsRequest $request, Model $operationRecord): View
    {
        $m = $request->module();
        $rel = Definition::relation($m);
        $rows = $rel ? $operationRecord->$rel()->get() : collect();
        if ($m === 'rosters' && WorkforceAccess::selfOnly($request->user(), $m)) {
            $rows = $rows->whereIn('employee_id', WorkforceAccess::employees($request->user(), $m)->pluck('id'));
        }

        return view('operations.show', $this->data($request, $m) + ['record' => $operationRecord, 'rows' => $rows]);
    }

    public function edit(OperationsRequest $request, Model $operationRecord): View
    {
        $m = $request->module();
        Gate::authorize('operations-action', [$m, $operationRecord, 'update']);
        $record = $operationRecord;
        $record->setAttribute('unit_id', Access::unitId($record, $m));
        if (in_array($m, ['production-tasks', 'packing-dispatch'], true)) {
            $record->setAttribute('depends_on_task_id', DB::table('production_task_dependencies')->where('production_task_id', $record->id)->value('depends_on_task_id'));
        }
        $rel = Definition::relation($m);
        $lines = $rel && Definition::lineFields($m) ? $record->$rel()->get()->map(fn ($row) => array_intersect_key($row->attributesToArray(), Definition::lineFields($m)))->all() : [];

        return view('operations.edit', $this->data($request, $m) + compact('record', 'lines'));
    }

    public function store(OperationsRequest $request, OperationsMutation $service): RedirectResponse
    {
        $r = $service->save($request);

        return to_route($request->module().'.show', $r)->with('success', 'Data berhasil disimpan.');
    }

    public function update(OperationsRequest $request, Model $operationRecord, OperationsMutation $service): RedirectResponse
    {
        $r = $service->save($request, $operationRecord);

        return to_route($request->module().'.show', $r)->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, Model $operationRecord, OperationsMutation $service): RedirectResponse
    {
        $m = $request->route()->defaults['module'];
        $service->action($m, $operationRecord, 'delete', []);

        return to_route($m.'.index')->with('success', 'Data berhasil dihapus.');
    }

    public function action(OperationsRequest $request, Model $operationRecord, OperationsMutation $service): RedirectResponse
    {
        $service->action($request->module(), $operationRecord, $request->route()->defaults['operation'], $request->validated());

        return to_route($request->module().'.show', $operationRecord)->with('success', 'Tindakan berhasil diproses.');
    }

    private function data(Request $request, string $m): array
    {
        $u = $request->user();
        $o = [];
        $o['unit_id'] = Access::units($u, $m)->orderBy('name')->pluck('name', 'id')->all();
        foreach (['destination_unit_id', 'production_unit_id', 'operational_unit_id'] as $key) {
            $o[$key] = $o['unit_id'];
        }
        if (Definition::production($m)) {
            $o['product_variant_id'] = ProductVariant::where('is_active', true)->whereHas('product', fn ($q) => $q->whereIn('brand_id', Access::units($u, $m)->select('brand_id'))->whereHas('brand', fn ($q) => $q->where('organization_id', $u->organization_id)))->get()->mapWithKeys(fn ($v) => [$v->id => $v->sku.' · '.$v->name])->all();
            $o['recipe_version_id'] = RecipeVersion::where('status', 'APPROVED')->whereHas('recipe', fn ($q) => $q->whereIn('brand_id', Access::units($u, $m)->select('brand_id'))->whereHas('brand', fn ($q) => $q->where('organization_id', $u->organization_id)))->with('recipe')->get()->mapWithKeys(fn ($v) => [$v->id => $v->recipe->name.' · v'.$v->version_no])->all();
            $plans = ProductionAccess::plans($u, $m)->get();
            $o['production_plan_id'] = $plans->pluck('number', 'id')->all();
            $o['demand_forecast_id'] = DemandForecast::whereIn('operational_unit_id', Access::units($u, $m)->select('id'))->whereHas('brand', fn ($q) => $q->where('organization_id', $u->organization_id))->where('status', 'APPROVED')->get()->mapWithKeys(fn ($f) => [$f->id => '#'.$f->id.' · '.$f->forecast_date->format('Y-m-d')])->all();
            $o['production_plan_line_id'] = ProductionAccess::lines($u, $m)->get()->mapWithKeys(fn ($l) => [$l->id => ($o['production_plan_id'][$l->production_plan_id] ?? '#'.$l->production_plan_id).' · baris #'.$l->id.' · target '.$l->target_quantity])->all();
            $schedules = ProductionSchedule::whereIn('production_plan_id', $plans->pluck('id'))->get();
            $o['production_schedule_id'] = $schedules->mapWithKeys(fn ($s) => [$s->id => '#'.$s->id.' · '.$s->starts_at->timezone(TradeMutation::timezone())->format('d M Y H:i')])->all();
            $o['depends_on_task_id'] = ProductionTask::whereIn('production_schedule_id', $schedules->pluck('id'))->pluck('name', 'id')->all();
            $o['production_batch_id'] = ProductionAccess::batches($u, $m)->where('status', 'AWAITING_QC')->pluck('batch_no', 'id')->all();
            $o['assigned_employee_id'] = Employee::where('organization_id', $u->organization_id)->where('status', 'ACTIVE')->whereIn('home_unit_id', Access::units($u, $m)->select('id'))->pluck('full_name', 'id')->all();
            $o['inventory_item_id'] = InventoryItem::withTrashed()->where('organization_id', $u->organization_id)->pluck('name', 'id')->all();
            $o['task_type'] = array_combine($m === 'packing-dispatch' ? ['PACKING', 'DISPATCH'] : ['PREPARATION', 'COOKING', 'CLEANING', 'PACKING', 'DISPATCH'], $m === 'packing-dispatch' ? ['Packing', 'Siap dispatch'] : ['Persiapan', 'Memasak', 'Pembersihan', 'Packing', 'Siap dispatch']);
            $o['check_type'] = ['FINAL' => 'Final', 'TEMPERATURE' => 'Suhu', 'WEIGHT' => 'Berat', 'VISUAL' => 'Visual'];
            $o['result'] = ['PASS' => 'Lulus', 'FAIL' => 'Gagal'];
        } else {
            $o['employee_id'] = WorkforceAccess::employees($u, $m)->where('status', 'ACTIVE')->pluck('full_name', 'id')->all();
            $o['to_employee_id'] = WorkforceAccess::employees($u, $m, false)->where('status', 'ACTIVE')->pluck('full_name', 'id')->all();
            $o['skill_id'] = Skill::where('organization_id', $u->organization_id)->pluck('name', 'id')->all();
            $o['position_id'] = Position::where('organization_id', $u->organization_id)->pluck('name', 'id')->all();
            $o['shift_template_id'] = ShiftTemplate::where('organization_id', $u->organization_id)->where('is_active', true)->pluck('name', 'id')->all();
            $o['roster_id'] = Roster::whereIn('operational_unit_id', Access::units($u, $m)->select('id'))->get()->mapWithKeys(fn ($r) => [$r->id => '#'.$r->id.' · '.$r->period_start->format('Y-m-d').' · '.$r->status])->all();
            $format = fn ($s) => [$s->id => '#'.$s->id.' · '.$s->starts_at->timezone(TradeMutation::timezone())->format('d M Y H:i').' · '.$s->status];
            $o['shift_assignment_id'] = WorkforceAccess::shifts($u, $m, $m !== 'open-shifts')->get()->mapWithKeys($format)->all();
            $o['from_assignment_id'] = $o['shift_assignment_id'];
            $o['to_assignment_id'] = WorkforceAccess::shifts($u, $m, false)->get()->mapWithKeys($format)->all();
            $o['attendance_record_id'] = WorkforceAccess::attendance($u, $m)->get()->mapWithKeys(fn ($r) => [$r->id => '#'.$r->id.' · '.$r->business_date->format('Y-m-d')])->all();
            $o['open_shift_id'] = WorkforceAccess::opens($u, $m)->where('status', 'OPEN')->with('shiftAssignment')->get()->mapWithKeys(fn ($r) => [$r->id => '#'.$r->id.' · '.$r->shiftAssignment->starts_at->timezone(TradeMutation::timezone())->format('d M Y H:i')])->all();
            $o['leave_type'] = ['ANNUAL' => 'Cuti tahunan', 'SICK' => 'Sakit', 'PERSONAL' => 'Izin pribadi', 'UNPAID' => 'Izin tanpa upah'];
        }

        return ['module' => $m, 'label' => Definition::labels()[$m], 'fields' => Definition::fields($m), 'lineFields' => Definition::lineFields($m), 'actions' => Definition::actions($m), 'options' => $o, 'timezone' => TradeMutation::timezone()];
    }
}
