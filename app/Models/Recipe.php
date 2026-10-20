<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recipes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_item_id');
    }

    public function recipeVersions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'recipe_id');
    }
}
