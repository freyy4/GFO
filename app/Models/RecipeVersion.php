<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeVersion extends Model
{
    use HasFactory;

    protected $table = 'recipe_versions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'yield_quantity' => 'decimal:4',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'calculated_cost' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function yieldUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'yield_uom_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recipeLinesAsRecipeVersion(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'recipe_version_id');
    }

    public function recipeLinesAsSubRecipeVersion(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'sub_recipe_version_id');
    }

    public function recipeCostSnapshots(): HasMany
    {
        return $this->hasMany(RecipeCostSnapshot::class, 'recipe_version_id');
    }

    public function productionPlanLines(): HasMany
    {
        return $this->hasMany(ProductionPlanLine::class, 'recipe_version_id');
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'recipe_version_id');
    }
}
