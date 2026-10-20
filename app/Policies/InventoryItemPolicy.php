<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;
use App\Support\CatalogAccess;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return CatalogAccess::canRead($user, 'inventory_items');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return CatalogAccess::items($user)->withTrashed()->whereKey($item->id)->exists();
    }

    public function create(User $user): bool
    {
        return CatalogAccess::canWrite($user, 'inventory_items', 'create');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return ! $item->trashed() && $this->view($user, $item) && CatalogAccess::canWrite($user, 'inventory_items', 'update');
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return ! $item->trashed() && $this->view($user, $item) && CatalogAccess::canWrite($user, 'inventory_items', 'delete');
    }

    public function restore(User $user, InventoryItem $item): bool
    {
        return $item->trashed() && $this->view($user, $item) && CatalogAccess::canWrite($user, 'inventory_items', 'delete');
    }
}
