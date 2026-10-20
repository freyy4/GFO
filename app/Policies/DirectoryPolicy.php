<?php

namespace App\Policies;

use App\Models\User;
use App\Support\DirectoryAccess;
use Illuminate\Database\Eloquent\Model;

abstract class DirectoryPolicy
{
    protected string $module;

    public function viewAny(User $user): bool
    {
        return DirectoryAccess::assignments($user, $this->module)->isNotEmpty();
    }

    public function view(User $user, Model $record): bool
    {
        $query = DirectoryAccess::query($user, $this->module);
        if ($this->module !== 'positions') {
            $query->withTrashed();
        }

        return $query->whereKey($record->id)->exists();
    }

    public function create(User $user): bool
    {
        return DirectoryAccess::write($user, $this->module, 'create');
    }

    public function update(User $user, Model $record): bool
    {
        return ! $record->getAttribute('deleted_at') && $this->view($user, $record) && DirectoryAccess::write($user, $this->module, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return ! $record->getAttribute('deleted_at') && $this->view($user, $record) && DirectoryAccess::write($user, $this->module, 'delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return (bool) $record->getAttribute('deleted_at') && $this->view($user, $record) && DirectoryAccess::write($user, $this->module, 'delete');
    }
}
