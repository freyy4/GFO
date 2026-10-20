<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\OperationalUnit;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingAccess
{
    public static function grants(User $u, string $m, string $a = 'view')
    {
        $roles = AccountingDefinition::payroll($m) ? ['HR'] : ['DIRECTOR', 'FINANCE', 'AUDITOR'];
        if (in_array($m, ['payslips', 'payroll-allocations'], true)) {
            $roles[] = 'FINANCE';
        }

        return PurchasingAccess::assignments($u)->filter(fn ($g) => ($g->operational_unit_id !== null || $g->role->scope_type === 'ORGANIZATION') && (($g->role->is_system && $g->role->code === 'SUPER_ADMIN') || $g->role->permissions->contains('code', str_replace('-', '_', $m).'.'.$a) || ($a === 'view' && in_array($g->role->code, $roles, true))));
    }

    public static function wide(User $u, string $m, string $a = 'view'): bool
    {
        return self::grants($u, $m, $a)->contains(fn ($g) => $g->operational_unit_id === null && $g->role->scope_type === 'ORGANIZATION');
    }

    public static function units(User $u, string $m, string $a = 'view'): Builder
    {
        $q = OperationalUnit::where('organization_id', $u->organization_id);
        if (! self::wide($u, $m, $a)) {
            $q->whereIn('id', self::grants($u, $m, $a)->pluck('operational_unit_id')->filter());
        }

return $q;
    }

    public static function employees(User $u, string $m): Builder
    {
        return Employee::where('organization_id', $u->organization_id)->whereIn('home_unit_id', self::units($u, $m)->select('id'));
    }

    public static function runs(User $u): Builder
    {
        return PayrollRun::whereHas('payrollPeriod', fn ($q) => $q->where('organization_id', $u->organization_id));
    }

    public static function slips(User $u, string $m): Builder
    {
        return Payslip::whereIn('payroll_run_id', self::runs($u)->select('id'))->whereIn('employee_id', self::employees($u, $m)->select('id'));
    }

    public static function query(User $u, string $m): Builder
    {
        $class = AccountingDefinition::MODELS[$m];
        $q = $class::query();
        if (self::grants($u, $m)->isEmpty()) {
            return $q->whereRaw('1=0');
        }

        return match ($m) {
            'payroll-periods','kpi-definitions','expense-categories' => $q->where('organization_id', $u->organization_id)->when($m === 'payroll-periods' && ! self::wide($u, $m), fn ($q) => $q->whereRaw('1=0')),
            'payroll-runs' => self::runs($u)->when(! self::wide($u, $m), fn ($q) => $q->whereRaw('1=0')),
            'payslips' => self::slips($u, $m),
            'payroll-allocations' => $q->whereIn('operational_unit_id', self::units($u, $m)->select('id'))->whereHas('payslip', fn ($q) => $q->whereIn('payroll_run_id', self::runs($u)->select('id'))->whereHas('employee', fn ($q) => $q->where('organization_id', $u->organization_id))),
            'kpi-targets' => $q->where('targetable_type', (new Employee)->getMorphClass())->whereIn('targetable_id', self::employees($u, $m)->select('id'))->whereHas('kpiDefinition', fn ($q) => $q->where('organization_id', $u->organization_id)),
            'kpi-results' => $q->where('measurable_type', (new Employee)->getMorphClass())->whereIn('measurable_id', self::employees($u, $m)->select('id'))->whereHas('kpiDefinition', fn ($q) => $q->where('organization_id', $u->organization_id)),
            'performance-scores','employee-recognitions' => $q->whereIn('employee_id', self::employees($u, $m)->select('id')),
            'expenses' => $q->where('organization_id', $u->organization_id)->where(fn ($q) => $q->whereIn('operational_unit_id', self::units($u, $m)->select('id'))->when(self::wide($u, $m), fn ($q) => $q->orWhereNull('operational_unit_id')))->whereHas('expenseCategory', fn ($q) => $q->where('organization_id', $u->organization_id)),
            'bills','recurring-obligations' => $q->whereIn('operational_unit_id', self::units($u, $m)->select('id'))->whereHas('expenseCategory', fn ($q) => $q->where('organization_id', $u->organization_id)),
            default => $q->whereIn('operational_unit_id', self::units($u, $m)->select('id')),
        };
    }

    public static function unitId(Model $r, string $m): ?int
    {
        return match ($m) {
            'payslips','performance-scores','employee-recognitions' => $r->employee?->home_unit_id,
            'kpi-targets' => Employee::find($r->targetable_id)?->home_unit_id,'kpi-results' => Employee::find($r->measurable_id)?->home_unit_id,
            default => $r->operational_unit_id,
        };
    }

    public static function unit(string $m, int $id, string $a = 'view'): OperationalUnit
    {
        $u = self::units(auth()->user(), $m, $a)->whereIn('id',self::units(auth()->user(),$m)->select('id'))->where('status','ACTIVE')->find($id);
        if (! $u) {
            StockLedger::fail('Unit tidak tersedia dalam izin tindakan Anda.','unit_id');
        }

return $u;
    }
}
