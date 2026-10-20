<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OpenShift;
use App\Models\OperationalUnit;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkforceAccess
{
    public static function grants(User $u, string $m, string $ability = 'view')
    {
        return PurchasingAccess::assignments($u)->filter(fn ($a) => ($a->operational_unit_id !== null || $a->role->scope_type === 'ORGANIZATION') && (($a->role->code === 'SUPER_ADMIN' && $a->role->is_system) || $a->role->permissions->contains('code', str_replace('-', '_', $m).'.'.$ability) || ($ability === 'view' && in_array($a->role->code, ['HR', 'OPERATIONS_MANAGER', 'KITCHEN_MANAGER', 'BOOTH_SUPERVISOR', 'CREW'], true))));
    }

    public static function units(User $u, string $m, string $ability = 'view'): Builder
    {
        $g = self::grants($u, $m, $ability);
        $q = OperationalUnit::where('organization_id', $u->organization_id);
        if (! $g->contains(fn ($a) => $a->operational_unit_id === null && $a->role->scope_type === 'ORGANIZATION')) {
            $q->whereIn('id', $g->pluck('operational_unit_id')->filter());
        }

        return $q;
    }

    public static function selfOnly(User $u, string $m): bool
    {
        return ! self::grants($u, $m)->contains(fn ($a) => $a->role->code !== 'CREW');
    }

    public static function employees(User $u, string $m, bool $self = true): Builder
    {
        $q = Employee::where('organization_id', $u->organization_id)->where(fn ($q) => $q->whereIn('home_unit_id', self::units($u, $m)->select('id'))->orWhereHas('employeeUnitAssignments', fn ($q) => $q->whereIn('operational_unit_id', self::units($u, $m)->select('id'))->whereDate('starts_on', '<=', now()->format('Y-m-d'))->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->format('Y-m-d')))));
        if ($self && self::selfOnly($u, $m)) {
            $q->where('user_id', $u->id);
        }

        return $q;
    }

    public static function shifts(User $u, string $m, bool $self = true): Builder
    {
        $q = ShiftAssignment::whereIn('operational_unit_id', self::units($u, $m)->select('id'))->where(fn ($q) => $q->whereNull('employee_id')->orWhereHas('employee', fn ($q) => $q->where('organization_id', $u->organization_id)));
        if ($self && self::selfOnly($u, $m)) {
            $q->whereIn('employee_id', self::employees($u, $m)->select('id'));
        }

        return $q;
    }

    public static function attendance(User $u, string $m): Builder
    {
        return AttendanceRecord::whereIn('operational_unit_id', self::units($u, $m)->select('id'))->whereIn('employee_id', self::employees($u, $m)->select('id'));
    }

    public static function opens(User $u, string $m): Builder
    {
        return OpenShift::whereIn('shift_assignment_id', self::shifts($u, $m, false)->select('id'));
    }

    public static function query(User $u, string $m): Builder
    {
        $class = WorkforceDefinition::MODELS[$m];
        $q = $class::query();
        if (self::grants($u, $m)->isEmpty()) {
            return $q->whereRaw('1=0');
        }

        return match ($m) {
            'shift-templates','skills' => $q->where('organization_id', $u->organization_id),
            'rosters' => $q->whereIn('operational_unit_id', self::units($u, $m)->select('id'))->when(self::selfOnly($u, $m), fn ($q) => $q->where('status', 'PUBLISHED')),
            'shift-assignments' => self::shifts($u, $m),'attendance-records' => self::attendance($u, $m),
            'leave-requests','employee-certifications' => $q->whereIn('employee_id', self::employees($u, $m)->select('id')),
            'employee-skills' => $q->whereIn('employee_id', self::employees($u, $m)->select('id'))->whereHas('skill', fn ($q) => $q->where('organization_id', $u->organization_id)),
            'attendance-corrections' => $q->whereIn('attendance_record_id', self::attendance($u, $m)->select('id')),
            'open-shifts' => self::opens($u, $m),
            'replacement-candidates','shift-claims' => $q->whereIn('open_shift_id', self::opens($u, $m)->select('id'))->whereIn('employee_id', self::employees($u, $m)->select('id')),
            'shift-swaps' => $q->whereIn('from_assignment_id', self::shifts($u, $m)->select('id'))->whereIn('to_employee_id', self::employees($u, $m, false)->select('id'))->where(fn ($q) => $q->whereNull('to_assignment_id')->orWhereIn('to_assignment_id', self::shifts($u, $m, false)->select('id')))
        };
    }

    public static function unitId(Model $r, string $m): ?int
    {
        return match ($m) {
            'shift-templates','skills' => null,'rosters','shift-assignments','attendance-records' => $r->operational_unit_id,
            'leave-requests','employee-skills','employee-certifications' => $r->employee->home_unit_id,
            'attendance-corrections' => $r->attendanceRecord->operational_unit_id,'open-shifts' => $r->shiftAssignment->operational_unit_id,
            'replacement-candidates','shift-claims' => $r->openShift->shiftAssignment->operational_unit_id,'shift-swaps' => $r->fromAssignment->operational_unit_id
        };
    }

    public static function write(User $u, string $m, string $a, ?Model $r = null): bool
    {
        if (self::grants($u, $m)->isEmpty()) {
            return false;
        }if (in_array($m, ['skills', 'shift-templates'], true)) {
            return self::grants($u, $m, $a)->contains(fn ($g) => $g->operational_unit_id === null && $g->role->scope_type === 'ORGANIZATION');
        }
        $q = self::units($u, $m, $a)->where('status', 'ACTIVE');
        if ($r) {
            $q->whereKey(self::unitId($r, $m));
        }

        return $q->exists();
    }
}
