<?php

namespace App\Support;

use App\Models\InventoryItem;
use App\Models\OperationalUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\RoleAssignment;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchasingAccess
{
    public static function forget(User $user): void
    {
        request()->attributes->remove('purchasing_roles_'.$user->id);
        request()->attributes->set('purchasing_refresh_'.$user->id, true);
    }

    public static function assignments(User $user): Collection
    {
        $key = 'purchasing_roles_'.$user->id;
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        if (! request()->attributes->get('purchasing_refresh_'.$user->id)) {
            $shared = request()->attributes->get('directory_assignments_'.$user->id) ?? request()->attributes->get('dashboard_assignments_'.$user->id);
            if ($shared instanceof Collection) {
                request()->attributes->set($key, $shared);

                return $shared;
            }
        }
        $rows = collect();
        if ($user->status === 'ACTIVE' && ! $user->trashed() && $user->organization()->where('status', 'ACTIVE')->exists()) {
            $rows = RoleAssignment::where('user_id', $user->id)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->whereHas('role', fn ($q) => $q->where('organization_id', $user->organization_id)->orWhere(fn ($q) => $q->whereNull('organization_id')->where('is_system', true)))
                ->where(fn ($q) => $q->whereNull('operational_unit_id')->orWhereHas('operationalUnit', fn ($q) => $q->where('organization_id', $user->organization_id)->where('status', 'ACTIVE')))
                ->with('role.permissions')->get();
        }
        request()->attributes->set($key, $rows);

        return $rows;
    }

    public static function grants(User $user, string $module, string $ability = 'view'): Collection
    {
        return self::assignments($user)->filter(function ($a) use ($module, $ability) {
            if ($a->operational_unit_id === null && $a->role->scope_type !== 'ORGANIZATION') {
                return false;
            }

            return ($a->role->code === 'SUPER_ADMIN' && $a->role->is_system)
                || $a->role->permissions->contains('code', str_replace('-', '_', $module).'.'.$ability)
                || ($ability === 'view' && ($a->role->code === 'PROCUREMENT' || (PurchasingDefinition::document($module) && $a->role->code === 'WAREHOUSE_MANAGER')));
        });
    }

    public static function wide(User $user, string $module, string $ability = 'view'): bool
    {
        return self::grants($user, $module, $ability)->contains(fn ($a) => $a->operational_unit_id === null && $a->role->scope_type === 'ORGANIZATION');
    }

    public static function units(User $user, string $module, string $ability = 'view'): Builder
    {
        $query = OperationalUnit::where('organization_id', $user->organization_id);
        if (! self::wide($user, $module, $ability)) {
            $query->whereIn('id', self::grants($user, $module, $ability)->pluck('operational_unit_id')->filter());
        }

        return $query;
    }

    public static function items(User $user, string $module, bool $active = true): Builder
    {
        $q = InventoryItem::where('organization_id', $user->organization_id);
        if ($active) {
            $q->where('is_active', true);
        } else {
            $q->withTrashed();
        }
        if (! self::wide($user, $module)) {
            $q->where(fn ($q) => $q->whereNull('brand_id')->orWhereIn('brand_id', self::units($user, $module)->select('brand_id')));
        }

        return $q;
    }

    public static function suppliers(User $user): Builder
    {
        return Supplier::where('organization_id', $user->organization_id);
    }

    public static function supplierItems(User $user, string $module): Builder
    {
        return SupplierItem::whereHas('supplier', fn ($q) => $q->withTrashed()->where('organization_id', $user->organization_id))
            ->whereIn('inventory_item_id', self::items($user, $module, false)->select('id'));
    }

    public static function orders(User $user, string $module): Builder
    {
        return PurchaseOrder::where('organization_id', $user->organization_id)->whereIn('destination_unit_id', self::units($user, $module)->select('id'))
            ->whereHas('supplier', fn ($q) => $q->withTrashed()->where('organization_id', $user->organization_id));
    }

    public static function requisitions(User $user, string $module): Builder
    {
        return PurchaseRequisition::where('organization_id', $user->organization_id)->whereIn('destination_unit_id', self::units($user, $module)->select('id'));
    }

    public static function query(User $user, string $module): Builder
    {
        $model = PurchasingDefinition::MODELS[$module];
        $q = $model::query();
        if (self::grants($user, $module)->isEmpty()) {
            return $q->whereRaw('1=0');
        }

        return match ($module) {
            'purchase-requisitions' => self::requisitions($user, $module),
            'purchase-orders' => self::orders($user, $module),
            'goods-receipts' => $q->whereIn('destination_unit_id', self::units($user, $module)->select('id'))
                ->whereHas('supplier', fn ($q) => $q->withTrashed()->where('organization_id', $user->organization_id))
                ->where(fn ($q) => $q->whereNull('purchase_order_id')->orWhereIn('purchase_order_id', self::orders($user, $module)->withTrashed()->select('id'))),
            'supplier-contracts' => $q->whereHas('supplier', fn ($q) => $q->withTrashed()->where('organization_id', $user->organization_id)),
            'supplier-items' => self::supplierItems($user, $module),
            'supplier-price-history' => $q->whereIn('supplier_item_id', self::supplierItems($user, $module)->select('id')),
        };
    }

    public static function canWrite(User $user, string $module, string $ability, ?int $unit = null): bool
    {
        if (self::grants($user, $module)->isEmpty()) {
            return false;
        }
        if (! PurchasingDefinition::document($module)) {
            return self::wide($user, $module, $ability) && self::wide($user, $module);
        }
        if ($unit !== null) {
            return self::units($user, $module, $ability)->whereKey($unit)->exists() && self::units($user, $module)->whereKey($unit)->exists();
        }

        return self::units($user, $module, $ability)->where('status', 'ACTIVE')->exists();
    }
}
