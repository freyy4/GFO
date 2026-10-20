<?php
namespace App\Policies;
use App\Models\Recipe;
use App\Models\User;
use App\Support\CatalogAccess;
use App\Support\ProductRecipeAccess;
class RecipePolicy
{
 public function viewAny(User $user): bool { return CatalogAccess::canRead($user, 'recipes'); }
 public function view(User $user, Recipe $record): bool { return ProductRecipeAccess::recipes($user)->withTrashed()->whereKey($record->id)->exists(); }
 public function create(User $user): bool { return CatalogAccess::canWrite($user, 'recipes', 'create'); }
 public function update(User $user, Recipe $record): bool { return ! $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'recipes', 'update'); }
 public function delete(User $user, Recipe $record): bool { return ! $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'recipes', 'delete'); }
 public function restore(User $user, Recipe $record): bool { return $record->trashed() && $this->view($user, $record) && CatalogAccess::canWrite($user, 'recipes', 'delete'); }
}
