<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    use HasFactory;

    protected $table = 'production_batches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'planned_quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
        ];
    }

    public function productionPlanLine(): BelongsTo
    {
        return $this->belongsTo(ProductionPlanLine::class, 'production_plan_line_id');
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'production_unit_id');
    }

    public function outputInventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'output_inventory_batch_id');
    }

    public function productionMaterialUsages(): HasMany
    {
        return $this->hasMany(ProductionMaterialUsage::class, 'production_batch_id');
    }

    public function productionQcChecks(): HasMany
    {
        return $this->hasMany(ProductionQCCheck::class, 'production_batch_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(WasteRecord::class, 'production_batch_id');
    }
}
