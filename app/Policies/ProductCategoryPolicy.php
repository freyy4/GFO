<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;
use App\Support\CatalogAccess;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return CatalogAccess::canRead($user, 'product_categories');
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return CatalogAccess::categories($user)->whereKey($category->id)->exists();
    }

    public function create(User $user): bool
    {
        return CatalogAccess::canWrite($user, 'product_categories', 'create');
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $this->view($user, $category) && CatalogAccess::canWrite($user, 'product_categories', 'update');
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $this->view($user, $category) && CatalogAccess::canWrite($user, 'product_categories', 'delete');
    }
}
