<?php
namespace App\Policies;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogAccess;
use App\Support\ProductRecipeAccess;
class ProductPolicy
{
 public function viewAny(User $user): bool { return CatalogAccess::canRead($user, 'products'); }
 public function view(User $user, Product $record): bool { return ProductRecipeAccess::products($user)->withTrashed()->whereKey($record->id)->exists(); }
 public function create(User $user): bool { return CatalogAccess::canWrite($user, 'products', 'create'); }
 public function update(User $user, Product $record): bool { return ! $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'products', 'update'); }
 public function delete(User $user, Product $record): bool { return ! $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'products', 'delete'); }
 public function restore(User $user, Product $record): bool { return $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'products', 'delete'); }
}
