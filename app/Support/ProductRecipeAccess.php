<?php

namespace App\Support;

use App\Models\{InventoryItem, Product, ProductVariant, Recipe, RecipeVersion, User};
use Illuminate\Database\Eloquent\Builder;

class ProductRecipeAccess
{
    public static function products(User $user): Builder
    {
        return Product::query()->whereIn('brand_id', CatalogAccess::brands($user, 'products')->withTrashed()->select('id'));
    }

    public static function variants(User $user, string $module = 'products'): Builder
    {
        return ProductVariant::query()->whereIn('product_id', Product::withTrashed()->whereIn('brand_id', CatalogAccess::brands($user, $module)->withTrashed()->select('id'))->select('id'));
    }

    public static function recipes(User $user): Builder
    {
        return Recipe::query()->whereIn('brand_id', CatalogAccess::brands($user, 'recipes')->withTrashed()->select('id'));
    }

    public static function versions(User $user): Builder
    {
        return RecipeVersion::query()->whereIn('recipe_id', self::recipes($user)->withTrashed()->select('id'));
    }

    public static function items(User $user, string $module): Builder
    {
        return InventoryItem::query()->where('organization_id', $user->organization_id)
            ->where(fn ($query) => $query->whereNull('brand_id')->orWhereIn('brand_id', CatalogAccess::brands($user, $module)->withTrashed()->select('id')));
    }
}
