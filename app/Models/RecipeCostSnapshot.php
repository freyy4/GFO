<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeCostSnapshot extends Model
{
    use HasFactory;

    protected $table = 'recipe_cost_snapshots';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cost_date' => 'date',
            'ingredient_cost' => 'decimal:4',
            'packaging_cost' => 'decimal:4',
            'labor_cost' => 'decimal:4',
            'overhead_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'calculation_detail' => 'array',
        ];
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }
}
