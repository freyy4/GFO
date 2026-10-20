<?php

namespace App\Support;

use App\Models\OperationalUnit;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardAccess
{
    public const LABELS = ['operations' => 'Operations Dashboard', 'hr' => 'HR Dashboard', 'growth' => 'Growth Dashboard'];

    private const ROLES = [
        'operations' => ['OPERATIONS_MANAGER', 'KITCHEN_MANAGER', 'WAREHOUSE_MANAGER', 'BOOTH_SUPERVISOR'],
        'hr' => ['HR'], 'growth' => ['DIRECTOR', 'MARKETING'],
    ];

    public static function assignments(User $user): Collection
    {
        $key = 'dashboard_assignments_'.$user->id;
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
                ->where(fn ($q) => $q->whereNull('operational_unit_id')->orWhereHas('operationalUnit', fn ($q) => $q->where('organization_id', $user->organization_id)->where('status', 'ACTIVE')))
                ->with('role.permissions')->get();
        }
        request()->attributes->set($key, $assignments);

        return $assignments;
    }

    public static function readers(User $user, string $domain): Collection
    {
        return self::assignments($user)->filter(function ($a) use ($domain) {
            if ($a->operational_unit_id === null && $a->role->scope_type !== 'ORGANIZATION') {
                return false;
            }
            if ($a->role->code === 'SUPER_ADMIN' && $a->role->is_system) {
                return true;
            }
            $domains = $domain === 'overview' ? array_keys(self::LABELS) : [$domain];
            foreach ($domains as $name) {
                if (in_array($a->role->code, self::ROLES[$name], true) || $a->role->permissions->contains('code', 'dashboards.'.$name.'.view')) {
                    return true;
                }
            }

            return $domain === 'overview' && $a->role->permissions->contains('code', 'operational_units.view');
        });
    }

    public static function wide(User $user, string $domain): bool
    {
        return self::readers($user, $domain)->contains(fn ($a) => $a->operational_unit_id === null && $a->role->scope_type === 'ORGANIZATION');
    }

    public static function units(User $user, string $domain): Builder
    {
        $query = OperationalUnit::where('organization_id', $user->organization_id);
        if (! self::wide($user, $domain)) {
            $query->whereIn('id', self::readers($user, $domain)->pluck('operational_unit_id')->filter());
        }

        return $query;
    }
}
