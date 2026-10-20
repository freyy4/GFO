<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionPlanLine extends Model
{
    use HasFactory;

    protected $table = 'production_plan_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'target_quantity' => 'decimal:4',
            'planned_batches' => 'decimal:4',
            'completed_quantity' => 'decimal:4',
        ];
    }

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    public function destinationUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'destination_unit_id');
    }

    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class, 'production_plan_line_id');
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'production_plan_line_id');
    }
}
