<?php
namespace App\Policies;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CatalogAccess;
use App\Support\ProductRecipeAccess;
class ProductVariantPolicy
{
 public function viewAny(User $user): bool { return CatalogAccess::canRead($user, 'products'); }
 public function view(User $user, ProductVariant $record): bool { return ProductRecipeAccess::variants($user)->withTrashed()->whereKey($record->id)->exists(); }
 public function create(User $user): bool { return CatalogAccess::canWrite($user, 'products', 'create'); }
 public function update(User $user, ProductVariant $record): bool { return ! $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'products', 'update'); }
 public function delete(User $user, ProductVariant $record): bool { return ! $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'products', 'delete'); }
 public function restore(User $user, ProductVariant $record): bool { return $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'products', 'delete'); }
}
