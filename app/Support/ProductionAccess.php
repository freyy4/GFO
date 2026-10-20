<?php

namespace App\Support;

use App\Models\OperationalUnit;
use App\Models\ProductionBatch;
use App\Models\ProductionPlan;
use App\Models\ProductionPlanLine;
use App\Models\ProductionSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductionAccess
{
    public static function grants(User $u, string $m, string $ability = 'view')
    {
        return PurchasingAccess::assignments($u)->filter(fn ($a) => ($a->operational_unit_id !== null || $a->role->scope_type === 'ORGANIZATION') && (($a->role->code === 'SUPER_ADMIN' && $a->role->is_system) || $a->role->permissions->contains('code', str_replace('-', '_', $m).'.'.$ability) || ($ability === 'view' && in_array($a->role->code, ['OPERATIONS_MANAGER', 'KITCHEN_MANAGER'], true))));
    }

    public static function units(User $u, string $m, string $ability = 'view'): Builder
    {
        $grants = self::grants($u, $m, $ability);
        $q = OperationalUnit::where('organization_id', $u->organization_id);
        if (! $grants->contains(fn ($a) => $a->operational_unit_id === null && $a->role->scope_type === 'ORGANIZATION')) {
            $q->whereIn('id', $grants->pluck('operational_unit_id')->filter());
        }

        return $q;
    }

    public static function plans(User $u, string $m): Builder
    {
        return ProductionPlan::whereIn('production_unit_id', self::units($u, $m)->select('id'))->whereHas('brand', fn ($q) => $q->where('organization_id', $u->organization_id));
    }

    public static function lines(User $u, string $m): Builder
    {
        return ProductionPlanLine::whereIn('production_plan_id', self::plans($u, $m)->select('id'))->where(fn ($q) => $q->whereNull('destination_unit_id')->orWhereIn('destination_unit_id', OperationalUnit::where('organization_id', $u->organization_id)->select('id')));
    }

    public static function batches(User $u, string $m): Builder
    {
        return ProductionBatch::whereIn('production_plan_line_id', self::lines($u, $m)->select('id'))->whereIn('production_unit_id', self::units($u, $m)->select('id'));
    }

    public static function query(User $u, string $m): Builder
    {
        $class = ProductionDefinition::MODELS[$m];
        $q = $class::query();
        if (self::grants($u, $m)->isEmpty()) {
            return $q->whereRaw('1=0');
        }

        return match ($m) {
            'demand-forecasts' => $q->whereIn('operational_unit_id', self::units($u, $m)->select('id'))->whereHas('brand', fn ($q) => $q->where('organization_id', $u->organization_id)),
            'production-plans' => self::plans($u, $m),
            'production-material-requirements','production-schedules' => $q->whereIn('production_plan_id', self::plans($u, $m)->select('id')),
            'production-tasks','packing-dispatch' => $q->whereIn('production_schedule_id', ProductionSchedule::whereIn('production_plan_id', self::plans($u, $m)->select('id'))->select('id'))->when($m === 'packing-dispatch', fn ($q) => $q->whereIn('task_type', ['PACKING', 'DISPATCH'])),
            'production-batches' => self::batches($u, $m),
            'production-qc' => $q->whereIn('production_batch_id', self::batches($u, $m)->select('id'))
        };
    }

    public static function unitId(Model $r, string $m): int
    {
        return match ($m) {
            'demand-forecasts' => $r->operational_unit_id,'production-plans','production-batches' => $r->production_unit_id,
            'production-material-requirements','production-schedules' => $r->productionPlan->production_unit_id,
            'production-tasks','packing-dispatch' => $r->productionSchedule->productionPlan->production_unit_id,
            'production-qc' => $r->productionBatch->production_unit_id
        };
    }

    public static function write(User $u, string $m, string $ability, ?Model $r = null): bool
    {
        if (self::grants($u, $m)->isEmpty()) {
            return false;
        }
        $q = self::units($u, $m, $ability)->where('status', 'ACTIVE');
        if ($r) {
            $q->whereKey(self::unitId($r, $m));
        }

        return $q->exists();
    }
}
