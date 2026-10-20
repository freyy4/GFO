<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingRequest;
use App\Models\Bill;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\KPIDefinition;
use App\Models\KPITarget;
use App\Models\PaymentSettlement;
use App\Models\PayrollPeriod;
use App\Models\PettyCashTransaction;
use App\Models\RecurringObligation;
use App\Models\Supplier;
use App\Support\AccountingAccess as Access;
use App\Support\AccountingDefinition as Definition;
use App\Support\AccountingMutation;
use App\Support\TradeMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AccountingController extends Controller
{
    public function index(AccountingRequest $request)
    {
        $m = $request->module();
        $filters = $request->validated();
        $data = $this->data($m);
        if ($m === 'finance') {
            return view('accounting.summary', $data + ['filters' => $filters, 'summary' => $this->summary($filters)]);
        }
        $q = Access::query($request->user(), $m);
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $column = match ($m) {
                'payroll-periods','kpi-definitions','expense-categories' => 'name','employee-recognitions' => 'title','expenses' => 'number','bills' => 'bill_no','recurring-obligations' => 'account_or_contract_no','payment-reconciliations' => 'reference_no',default => 'id'
            };
            $q->where(function ($q) use ($term, $column, $m) {
                $column === 'id' ? $q->where('id', ctype_digit($term) ? $term : 0) : $q->where($column, 'like', '%'.$term.'%');
                if (in_array($m, ['payslips', 'performance-scores', 'employee-recognitions'], true)) {
                    $q->orWhereHas('employee', fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_no', 'like', '%'.$term.'%'));
                }
                if (in_array($m, ['kpi-targets', 'kpi-results'], true)) {
                    $q->orWhereHas('kpiDefinition', fn ($q) => $q->where('name', 'like', '%'.$term.'%'));
                }
            });
        }
        if (! empty($filters['status'])) {
            $m === 'kpi-definitions' ? $q->where('is_active', $filters['status'] === 'ACTIVE') : $q->where('status', $filters['status']);
        }
        if (! empty($filters['unit_id'])) {
            abort_unless(Access::units($request->user(), $m)->whereKey($filters['unit_id'])->exists(), 403);
            $id = $filters['unit_id'];
            if (in_array($m, ['payslips', 'performance-scores', 'employee-recognitions'], true)) {
                $q->whereHas('employee', fn ($q) => $q->where('home_unit_id', $id));
            } elseif (in_array($m, ['kpi-targets', 'kpi-results'], true)) {
                $q->whereIn($m === 'kpi-targets' ? 'targetable_id' : 'measurable_id', Employee::where('organization_id', $request->user()->organization_id)->where('home_unit_id', $id)->select('id'));
            } elseif (! Definition::global($m)) {
                $q->where('operational_unit_id', $id);
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (! empty($filters[$key])) {
                $q->whereDate('created_at', $op, $filters[$key]);
            }
        }

        return view('accounting.index', $data + ['filters' => $filters, 'records' => $q->orderByDesc('id')->paginate(15)->withQueryString()]);
    }

    public function create(AccountingRequest $request)
    {
        $m = $request->module();
        Gate::authorize('accounting-create', $m);
        $class = Definition::MODELS[$m];

        return view('accounting.create', $this->data($m) + ['record' => new $class]);
    }

    public function edit(AccountingRequest $request, Model $accountingRecord)
    {
        $m = $request->module();
        Gate::authorize('accounting-action', [$m, $accountingRecord, 'update']);
        $accountingRecord->setAttribute('unit_id', Access::unitId($accountingRecord, $m));
        if ($m === 'kpi-targets') {
            $accountingRecord->setAttribute('employee_id', $accountingRecord->targetable_id);
        }

return view('accounting.edit', $this->data($m) + ['record' => $accountingRecord]);
    }

    public function show(AccountingRequest $request, Model $accountingRecord)
    {
        $m = $request->module();
        $rows = match ($m) {
            'payroll-runs' => $accountingRecord->payslips()->whereHas('employee', fn ($q) => $q->where('organization_id', $request->user()->organization_id))->get(),'payslips' => $accountingRecord->payrollComponents()->get(),'recurring-obligations' => $accountingRecord->bills()->where('operational_unit_id', $accountingRecord->operational_unit_id)->get(),default => collect()
        };

        return view('accounting.show', $this->data($m) + ['record' => $accountingRecord, 'rows' => $rows]);
    }

    public function store(AccountingRequest $request, AccountingMutation $service)
    {
        $r = $service->execute($request);

        return to_route($request->module().'.show', $r)->with('success', 'Data berhasil disimpan.');
    }

    public function update(AccountingRequest $request, Model $accountingRecord, AccountingMutation $service)
    {
        $r = $service->execute($request, $accountingRecord);

        return to_route($request->module().'.show', $r)->with('success', 'Data diperbarui.');
    }

    public function action(AccountingRequest $request, Model $accountingRecord, AccountingMutation $service)
    {
        $service->execute($request, $accountingRecord, $request->route()->defaults['operation']);

        return to_route($request->module().'.show', $accountingRecord)->with('success', 'Tindakan berhasil dicatat.');
    }

    public function destroy(AccountingRequest $request, Model $accountingRecord, AccountingMutation $service)
    {
        $service->execute($request, $accountingRecord, 'delete');

        return to_route($request->module().'.index')->with('success', 'Data dihapus.');
    }

    private function data(string $m): array
    {
        $u = auth()->user();
        $o = Definition::options();
        $o['type'] = array_intersect_key($o['type'], array_flip($m === 'employee-recognitions' ? ['REWARD', 'RECOGNITION'] : ['RENT', 'UTILITY', 'SERVICE', 'OTHER']));
        $o['unit_id'] = Access::units($u, $m)->orderBy('name')->pluck('name', 'id')->all();
        $o['operational_unit_id'] = $o['unit_id'];
        if (Definition::payroll($m)) {
            $o['employee_id'] = Access::employees($u, $m)->orderBy('full_name')->pluck('full_name', 'id')->all();
            $o['targetable_id'] = $o['employee_id'];
            $o['measurable_id'] = $o['employee_id'];
            $o['payroll_period_id'] = PayrollPeriod::where('organization_id', $u->organization_id)->where('status', 'OPEN')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->starts_on->format('Y-m-d').' / '.$p->ends_on->format('Y-m-d')])->all();
            $o['kpi_definition_id'] = KPIDefinition::where('organization_id', $u->organization_id)->where('is_active', true)->pluck('name', 'id')->all();
            $o['target_id'] = KPITarget::where('targetable_type', (new Employee)->getMorphClass())->whereIn('targetable_id', Access::employees($u, $m)->select('id'))->whereHas('kpiDefinition', fn ($q) => $q->where('organization_id', $u->organization_id))->with('kpiDefinition')->get()->mapWithKeys(fn ($t) => [$t->id => ($o['employee_id'][$t->targetable_id] ?? '#'.$t->targetable_id).' · '.$t->kpiDefinition->name.' · '.$t->period_start->format('Y-m-d').' / '.$t->period_end->format('Y-m-d')])->all();
        } else {
            $o['expense_category_id'] = ExpenseCategory::where('organization_id', $u->organization_id)->pluck('name', 'id')->all();
            $o['supplier_id'] = Supplier::where('organization_id', $u->organization_id)->where('status', 'ACTIVE')->pluck('name', 'id')->all();
            $o['recurring_obligation_id'] = RecurringObligation::whereIn('operational_unit_id', Access::units($u, $m)->select('id'))->whereHas('expenseCategory', fn ($q) => $q->where('organization_id', $u->organization_id))->where('status', 'ACTIVE')->get()->mapWithKeys(fn ($r) => [$r->id => '#'.$r->id.' · '.($o['unit_id'][$r->operational_unit_id] ?? '').' · '.$r->type.' · '.$r->account_or_contract_no])->all();
        }

        return ['module' => $m, 'label' => Definition::LABELS[$m], 'fields' => Definition::fields($m), 'actions' => Definition::actions($m), 'options' => $o, 'timezone' => TradeMutation::timezone()];
    }

    private function summary(array $filters): array
    {
        $u = auth()->user();
        $units = Access::units($u, 'finance');
        if (! empty($filters['unit_id'])) {
            abort_unless((clone $units)->whereKey($filters['unit_id'])->exists(), 403);
            $units->whereKey($filters['unit_id']);
        }$ids = $units->pluck('id');
        $from = $filters['from'] ?? now(TradeMutation::timezone())->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now(TradeMutation::timezone())->toDateString();
        $expenses = Expense::where('organization_id', $u->organization_id)->whereIn('operational_unit_id', $ids)->whereIn('status', ['APPROVED', 'PAID'])->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)->get();
        $total = '0';
        foreach ($expenses as $e) {
            $total = bcadd($total, $e->amount, 2);
        }
        $bills = Bill::whereIn('operational_unit_id', $ids)->where('status', 'UNPAID')->whereHas('expenseCategory', fn ($q) => $q->where('organization_id', $u->organization_id))->get();
        $unpaid = '0';
        $overdue = '0';
        foreach ($bills as $bill) {
            $unpaid = bcadd($unpaid, $bill->amount, 2);
            if ($bill->due_date->format('Y-m-d') < now(TradeMutation::timezone())->toDateString()) {
                $overdue = bcadd($overdue, $bill->amount, 2);
            }
        }
        $balance = '0';
        foreach (PettyCashTransaction::whereIn('operational_unit_id', $ids)->get() as $t) {
            $balance = bcadd($balance, $t->type === 'IN' ? $t->amount : '-'.$t->amount, 2);
        }
        $variance = '0';
        foreach (PaymentSettlement::whereIn('operational_unit_id',$ids)->where('status','SETTLED')->whereDate('period_end','>=',$from)->whereDate('period_end','<=',$to)->get() as $s) {
            $variance = bcadd($variance,$s->variance_amount,2);
        }

        return ['from' => $from, 'to' => $to, 'Expense disetujui/dibayar (periode)' => $total, 'Tagihan belum dibayar (seluruh tanggal)' => $unpaid, 'Tagihan lewat jatuh tempo (saat ini)' => $overdue, 'Saldo petty cash (saat ini)' => $balance, 'Selisih settlement (periode)' => $variance];
    }
}
