<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\InventoryItem;
use App\Models\ProductCategory;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CatalogAccess
{
    private const READ_ROLES = [
        'products' => ['SUPER_ADMIN', 'OPERATIONS_MANAGER', 'KITCHEN_MANAGER', 'BOOTH_SUPERVISOR', 'MARKETING'],
        'recipes' => ['SUPER_ADMIN', 'OPERATIONS_MANAGER', 'KITCHEN_MANAGER'],
        'product_categories' => ['SUPER_ADMIN', 'OPERATIONS_MANAGER', 'KITCHEN_MANAGER', 'MARKETING'],
        'inventory_items' => ['SUPER_ADMIN', 'OPERATIONS_MANAGER', 'KITCHEN_MANAGER', 'WAREHOUSE_MANAGER', 'PROCUREMENT'],
    ];

    public static function forget(User $user): void
    {
        request()->attributes->remove('catalog_assignments_'.$user->id);
    }

    private static function assignments(User $user): Collection
    {
        $key = 'catalog_assignments_'.$user->id;
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        $assignments = collect();
        if ($user->status === 'ACTIVE' && ! $user->trashed() && $user->organization()->where('status', 'ACTIVE')->exists()) {
            $assignments = RoleAssignment::query()->where('user_id', $user->id)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('operational_unit_id')->orWhereHas('operationalUnit', fn ($unit) => $unit
                    ->where('organization_id', $user->organization_id)->where('status', 'ACTIVE')))
                ->whereHas('role', fn ($query) => $query->where('organization_id', $user->organization_id)
                    ->orWhere(fn ($query) => $query->whereNull('organization_id')->where('is_system', true)))
                ->with(['role.permissions', 'operationalUnit'])->get();
        }
        request()->attributes->set($key, $assignments);

        return $assignments;
    }

    private static function readers(User $user, string $module): Collection
    {
        return self::assignments($user)->filter(fn ($assignment) => ($assignment->operational_unit_id !== null || $assignment->role->scope_type === 'ORGANIZATION')
            && (in_array($assignment->role->code, self::READ_ROLES[$module], true)
                || $assignment->role->permissions->contains('code', $module.'.view')));
    }

    public static function canRead(User $user, string $module): bool
    {
        return self::readers($user, $module)->isNotEmpty();
    }

    private static function organizationWide(User $user, string $module): bool
    {
        return self::readers($user, $module)->contains(fn ($assignment) => $assignment->operational_unit_id === null && $assignment->role->scope_type === 'ORGANIZATION');
    }

    public static function canWrite(User $user, string $module, string $ability): bool
    {
        if (! self::organizationWide($user, $module)) {
            return false;
        }

        return self::assignments($user)->contains(fn ($assignment) => $assignment->operational_unit_id === null
            && $assignment->role->scope_type === 'ORGANIZATION'
            && (($assignment->role->code === 'SUPER_ADMIN' && $assignment->role->is_system)
                || $assignment->role->permissions->contains('code', $module.'.'.$ability)));
    }

    public static function abilities(User $user, string $module): array
    {
        return collect(['create', 'update', 'delete'])->mapWithKeys(fn ($ability) => [$ability => self::canWrite($user, $module, $ability)])->all();
    }

    public static function brands(User $user, string $module): Builder
    {
        $query = Brand::query()->where('organization_id', $user->organization_id);
        if (! self::canRead($user, $module)) {
            return $query->whereRaw('1 = 0');
        }
        if (! self::organizationWide($user, $module)) {
            $query->whereIn('id', self::readers($user, $module)->pluck('operationalUnit.brand_id')->filter()->unique());
        }

        return $query;
    }

    public static function categories(User $user): Builder
    {
        return ProductCategory::query()->whereIn('brand_id', self::brands($user, 'product_categories')->withTrashed()->select('id'));
    }

    public static function items(User $user): Builder
    {
        $query = InventoryItem::query()->where('organization_id', $user->organization_id);
        if (! self::canRead($user, 'inventory_items')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn ($query) => $query->whereNull('brand_id')
            ->orWhereIn('brand_id', self::brands($user, 'inventory_items')->withTrashed()->select('id')));
    }
}
