<?php

namespace App\Policies;

use App\Models\OperationalUnit;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OperationalUnitAccess
{
    public static function assignments(User $user, string $ability): Builder
    {
        return RoleAssignment::query()->where('user_id', $user->id)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereHas('role', function ($query) use ($user, $ability) {
                $query->where('organization_id', $user->organization_id)
                    ->whereHas('permissions', fn ($permissions) => $permissions->where('code', 'operational_units.'.$ability));
            });
    }

    public static function eligible(User $user): bool
    {
        return $user->status === 'ACTIVE' && ! $user->trashed()
            && $user->organization()->where('status', 'ACTIVE')->exists();
    }

    public static function organizationWide(User $user, string $ability): bool
    {
        return self::eligible($user) && self::assignments($user, $ability)
            ->whereNull('operational_unit_id')->whereHas('role', fn ($query) => $query->where('scope_type', 'ORGANIZATION'))->exists();
    }

    public static function query(User $user, string $ability = 'view'): Builder
    {
        $query = OperationalUnit::query()->where('organization_id', $user->organization_id);
        if (! self::eligible($user)) {
            return $query->whereRaw('1 = 0');
        }
        if (! self::organizationWide($user, $ability)) {
            $query->whereIn('id', self::assignments($user, $ability)->whereNotNull('operational_unit_id')->select('operational_unit_id'));
        }

        return $query;
    }
}
