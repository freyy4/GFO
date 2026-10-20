<?php
namespace App\Policies;
use App\Models\RecipeVersion;
use App\Models\User;
use App\Support\CatalogAccess;
use App\Support\ProductRecipeAccess;
class RecipeVersionPolicy
{
 public function viewAny(User $user): bool { return CatalogAccess::canRead($user, 'recipes'); }
 public function view(User $user, RecipeVersion $record): bool { return ProductRecipeAccess::versions($user)->whereKey($record->id)->exists(); }
 public function create(User $user): bool { return CatalogAccess::canWrite($user, 'recipes', 'create'); }
 public function update(User $user, RecipeVersion $record): bool { return $record->status === 'DRAFT' && $this->view($user, $record) && CatalogAccess::canWrite($user, 'recipes', 'update'); }
 public function delete(User $user, RecipeVersion $record): bool { return $record->status === 'DRAFT' && $this->view($user, $record) && CatalogAccess::canWrite($user, 'recipes', 'delete'); }
 public function approve(User $user, RecipeVersion $record): bool { return $record->status === 'DRAFT' && $this->view($user, $record) && CatalogAccess::canWrite($user, 'recipes', 'approve'); }
}
