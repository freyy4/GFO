<?php

namespace App\Support;

use App\Models\CashRegister;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\OperationalUnit;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TradeAccess
{
    public static function grants(User $user, string $module, string $ability = 'view'): Collection
    {
        return PurchasingAccess::assignments($user)->filter(function ($a) use ($module, $ability) {
            if ($a->operational_unit_id === null && $a->role->scope_type !== 'ORGANIZATION') {
                return false;
            }
            $roles = TradeDefinition::inventory($module) ? ['OPERATIONS_MANAGER', 'KITCHEN_MANAGER', 'WAREHOUSE_MANAGER', 'BOOTH_SUPERVISOR'] : ['OPERATIONS_MANAGER', 'BOOTH_SUPERVISOR', 'FINANCE'];

            return ($a->role->is_system && $a->role->code === 'SUPER_ADMIN')
                || $a->role->permissions->contains('code', str_replace('-', '_', $module).'.'.$ability)
                || ($module === 'sales-orders' && $a->role->permissions->contains('code', 'pos.'.$ability))
                || ($ability === 'view' && in_array($a->role->code, $roles, true));
        });
    }

    public static function units(User $user, string $module, string $ability = 'view'): Builder
    {
        $grants = self::grants($user, $module, $ability);
        $q = OperationalUnit::where('organization_id', $user->organization_id);
        if (! $grants->contains(fn ($a) => $a->operational_unit_id === null && $a->role->scope_type === 'ORGANIZATION')) {
            $q->whereIn('id', $grants->pluck('operational_unit_id')->filter());
        }

        return $q;
    }

    public static function items(User $user): Builder
    {
        return InventoryItem::where('organization_id', $user->organization_id);
    }

    public static function query(User $user, string $m): Builder
    {
        $class = TradeDefinition::MODELS[$m];
        $q = $class::query();
        if (self::grants($user, $m)->isEmpty()) {
            return $q->whereRaw('1=0');
        }
        $units = self::units($user, $m)->select('id');
        if ($m === 'customers') {
            return $q->where('organization_id', $user->organization_id);
        }
        if ($m === 'inventory-batches') {
            return $q->whereIn('inventory_item_id', self::items($user)->select('id'));
        }
        if ($m === 'stock-transfers') {
            return $q->where('organization_id', $user->organization_id)->whereIn('source_unit_id', $units)->whereIn('destination_unit_id', self::units($user, $m)->select('id'));
        }
        if ($m === 'register-sessions') {
            return $q->whereIn('cash_register_id', CashRegister::whereIn('operational_unit_id', $units)->select('id'));
        }
        if (in_array($m, ['sales-payments', 'sales-refunds'], true)) {
            return $q->whereIn('sales_order_id', SalesOrder::where('organization_id', $user->organization_id)->whereIn('operational_unit_id', $units)->select('id'));
        }
        $q->whereIn('operational_unit_id', $units);
        if (in_array($m, ['sales-orders', 'inventory-movements'], true)) {
            $q->where('organization_id', $user->organization_id);
        }
        if (in_array($m, ['inventory', 'inventory-balances', 'inventory-movements', 'inventory-reservations', 'waste-records'], true)) {
            $q->whereIn('inventory_item_id', self::items($user)->withTrashed()->select('id'))
                ->where(fn ($q) => $q->whereNull('inventory_batch_id')->orWhereIn('inventory_batch_id', InventoryBatch::whereIn('inventory_item_id', self::items($user)->withTrashed()->select('id'))->select('id')));
        }

        return $q;
    }

    public static function unitIds(Model $r, string $m): array
    {
        return match ($m) {
            'stock-transfers' => [$r->source_unit_id, $r->destination_unit_id],
            'register-sessions' => [$r->cashRegister->operational_unit_id],
            'sales-refunds','sales-payments' => [$r->salesOrder->operational_unit_id],
            'customers','inventory-batches' => [],default => [$r->operational_unit_id]
        };
    }

    public static function write(User $user, string $m, string $ability, ?Model $r = null): bool
    {
        if (self::grants($user, $m)->isEmpty() || self::grants($user, $m, $ability)->isEmpty()) {
            return false;
        }
        if (in_array($m, ['customers', 'inventory-batches'], true)) {
            return self::grants($user, $m, $ability)->contains(fn ($a) => $a->operational_unit_id === null && $a->role->scope_type === 'ORGANIZATION');
        }
        if (! $r) {
            return self::units($user, $m, $ability)->where('status', 'ACTIVE')->exists();
        }
        foreach (self::unitIds($r, $m) as $id) {
            if (! self::units($user, $m, $ability)->whereKey($id)->exists()) {
                return false;
            }
        }

        return true;
    }
}
