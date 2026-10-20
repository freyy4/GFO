<?php

namespace App\Policies;

use App\Models\OperationalUnit;
use App\Models\User;

class OperationalUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return OperationalUnitAccess::organizationWide($user, 'view') || OperationalUnitAccess::query($user)->exists();
    }

    public function view(User $user, OperationalUnit $operationalUnit): bool
    {
        return OperationalUnitAccess::query($user)->whereKey($operationalUnit->id)->exists();
    }

    public function create(User $user): bool
    {
        return OperationalUnitAccess::organizationWide($user, 'view') && OperationalUnitAccess::organizationWide($user, 'create');
    }

    public function update(User $user, OperationalUnit $operationalUnit): bool
    {
        return $this->view($user, $operationalUnit)
            && OperationalUnitAccess::query($user, 'update')->whereKey($operationalUnit->id)->exists();
    }

    public function delete(User $user, OperationalUnit $operationalUnit): bool
    {
        return $this->view($user, $operationalUnit)
            && OperationalUnitAccess::query($user, 'delete')->whereKey($operationalUnit->id)->exists();
    }
}
