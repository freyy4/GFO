<?php

namespace App\Support;

use App\Models\OperationalUnit;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DirectoryAccess
{
    public static function forget(User $user): void
    {
        request()->attributes->remove('directory_assignments_'.$user->id);
    }

    private static function activeAssignments(User $user): Collection
    {
        $key = 'directory_assignments_'.$user->id;
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        $assignments = collect();
        if ($user->status === 'ACTIVE' && ! $user->trashed() && $user->organization()->where('status', 'ACTIVE')->exists()) {
            $assignments = RoleAssignment::where('user_id', $user->id)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->whereHas('role', fn ($q) => $q->where('organization_id', $user->organization_id)
                    ->orWhere(fn ($q) => $q->whereNull('organization_id')->where('is_system', true)))
                ->where(fn ($q) => $q->whereNull('operational_unit_id')->orWhereHas('operationalUnit', fn ($unit) => $unit
                    ->where('organization_id', $user->organization_id)->where('status', 'ACTIVE')))
                ->with('role.permissions')->get();
        }
        request()->attributes->set($key, $assignments);

        return $assignments;
    }

    public static function assignments(User $user, string $module, string $ability = 'view'): Collection
    {
        return self::activeAssignments($user)->filter(function ($assignment) use ($module, $ability) {
            $role = $assignment->role;
            if ($assignment->operational_unit_id === null && $role->scope_type !== 'ORGANIZATION') {
                return false;
            }

            return ($role->code === 'SUPER_ADMIN' && $role->is_system)
                || $role->permissions->contains('code', $module.'.'.$ability)
                || ($ability === 'view' && $role->code === ($module === 'suppliers' ? 'PROCUREMENT' : 'HR'));
        });
    }

    public static function wide(User $user, string $module, string $ability = 'view'): bool
    {
        return self::assignments($user, $module, $ability)->contains(fn ($a) => $a->operational_unit_id === null && $a->role->scope_type === 'ORGANIZATION');
    }

    public static function write(User $user, string $module, string $ability): bool
    {
        return self::wide($user, $module) && self::wide($user, $module, $ability);
    }

    public static function query(User $user, string $module): Builder
    {
        $class = DirectoryDefinition::model($module);
        $query = $class::where('organization_id', $user->organization_id);
        $assignments = self::assignments($user, $module);
        if ($assignments->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }
        if ($module === 'employees' && ! self::wide($user, $module)) {
            $query->whereIn('home_unit_id', $assignments->pluck('operational_unit_id')->filter());
        }

        return $query;
    }

    public static function units(User $user): Builder
    {
        $query = OperationalUnit::where('organization_id', $user->organization_id);
        if (! self::wide($user, 'employees')) {
            $query->whereIn('id', self::assignments($user, 'employees')->pluck('operational_unit_id')->filter());
        }

        return $query;
    }
}
