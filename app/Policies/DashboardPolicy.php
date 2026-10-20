<?php

namespace App\Policies;

use App\Models\User;
use App\Support\DashboardAccess;

class DashboardPolicy
{
    public function view(User $user, string $domain = 'overview'): bool
    {
        if ($user->status !== 'ACTIVE' || $user->trashed()) {
            return false;
        }

        return $domain === 'overview' || DashboardAccess::readers($user, $domain)->isNotEmpty();
    }
}
