<?php

namespace App\Policies;

use App\Models\User;

class ProfilePolicy
{
    public function manage(User $user): bool
    {
        return ! $user->trashed() && $user->status === 'ACTIVE' && $user->organization_id !== null
            && $user->organization()->where('status', 'ACTIVE')->exists();
    }
}
