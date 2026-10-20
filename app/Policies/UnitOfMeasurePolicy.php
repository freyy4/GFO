<?php

namespace App\Policies;

use App\Models\RoleAssignment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UnitOfMeasurePolicy
{
    public function viewAny(User $user): bool
    {
        if (! $this->eligible($user)) {
            return false;
        }

        return $this->assignments($user)->whereHas('role', fn ($query) => $query
            ->whereIn('code', ['SUPER_ADMIN', 'OPERATIONS_MANAGER', 'KITCHEN_MANAGER', 'WAREHOUSE_MANAGER', 'PROCUREMENT'])
            ->orWhereHas('permissions', fn ($permissions) => $permissions->where('code', 'uoms.view')))->exists();
    }

    public function view(User $user, UnitOfMeasure $uom): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        if (! $this->eligible($user)) {
            return false;
        }

        // UOM has no tenant columns: only an organization-wide system administrator may change this shared catalog.
        return $this->assignments($user)->whereNull('operational_unit_id')->whereHas('role', fn ($query) => $query
            ->where('code', 'SUPER_ADMIN')->where('is_system', true)->where('scope_type', 'ORGANIZATION'))->exists();
    }

    public function update(User $user, UnitOfMeasure $uom): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, UnitOfMeasure $uom): bool
    {
        return $this->create($user);
    }

    private function eligible(User $user): bool
    {
        return $user->status === 'ACTIVE' && ! $user->trashed()
            && $user->organization()->where('status', 'ACTIVE')->exists();
    }

    private function assignments(User $user): Builder
    {
        return RoleAssignment::query()->where('user_id', $user->id)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('operational_unit_id')->orWhereHas('operationalUnit', fn ($unit) => $unit
                ->where('organization_id', $user->organization_id)->where('status', 'ACTIVE')))
            ->whereHas('role', fn ($query) => $query->where('organization_id', $user->organization_id)
                ->orWhere(fn ($query) => $query->whereNull('organization_id')->where('is_system', true)));
    }
}
