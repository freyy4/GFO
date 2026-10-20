<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\OperationalUnit;
use Carbon\CarbonImmutable;

class OperationsContext
{
    public static function dateTime(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, TradeMutation::timezone())->utc();
    }

    public static function unit(string $m, int $id, string $a = 'view'): OperationalUnit
    {
        $u = OperationsAccess::units(auth()->user(), $m, $a)->whereIn('id', OperationsAccess::units(auth()->user(), $m)->select('id'))->where('status', 'ACTIVE')->find($id);
        if (! $u) {
            StockLedger::fail('Unit tidak tersedia dalam izin tindakan Anda.', 'unit_id');
        }

        return $u;
    }

    public static function employeeAt(int $id, int $unit, $start, $end): Employee
    {
        $e = Employee::where('organization_id', auth()->user()->organization_id)->where('status', 'ACTIVE')->where(fn ($q) => $q->whereNull('joined_on')->orWhereDate('joined_on', '<=', $start->format('Y-m-d')))->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $end->format('Y-m-d')))->lockForUpdate()->find($id);
        if (! $e || ! ($e->home_unit_id === $unit || $e->employeeUnitAssignments()->where('operational_unit_id', $unit)->whereDate('starts_on', '<=', $start->format('Y-m-d'))->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $end->format('Y-m-d')))->exists())) {
            StockLedger::fail('Karyawan tidak aktif atau tidak ditugaskan pada unit/periode tersebut.', 'employee_id');
        }

        return $e;
    }
}
