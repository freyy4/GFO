<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaterialRequirement extends Model
{
    use HasFactory;

    protected $table = 'production_material_requirements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:4',
            'available_quantity_snapshot' => 'decimal:4',
            'shortage_quantity' => 'decimal:4',
        ];
    }

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
