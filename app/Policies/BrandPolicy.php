<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'ACTIVE' && ! $user->trashed()
            && $user->organization()->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->viewAny($user) && (int) $user->organization_id === (int) $brand->organization_id;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->view($user, $brand) && ! $brand->trashed() && $brand->status !== 'ARCHIVED';
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->view($user, $brand) && ! $brand->trashed();
    }

    public function restore(User $user, Brand $brand): bool
    {
        return $this->view($user, $brand) && $brand->trashed();
    }

    public function activate(User $user, Brand $brand): bool
    {
        return $this->update($user, $brand) && in_array($brand->status, ['DRAFT', 'INACTIVE'], true);
    }

    public function deactivate(User $user, Brand $brand): bool
    {
        return $this->update($user, $brand) && $brand->status === 'ACTIVE';
    }
}
