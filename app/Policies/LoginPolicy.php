<?php

namespace App\Policies;

use App\Models\User;

class LoginPolicy
{
    public function login(User $user): bool
    {
        return ! $user->trashed() && $user->status === 'ACTIVE' && ! $user->mfa_enabled
            && $user->organization()->where('status', 'ACTIVE')->exists();
    }

    public function logout(User $user): bool
    {
        return $user->exists;
    }
}
