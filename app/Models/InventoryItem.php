<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reorder_point' => 'decimal:4',
            'safety_stock' => 'decimal:4',
            'standard_cost' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'inventory_item_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'output_item_id');
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'inventory_item_id');
    }

    public function supplierItems(): HasMany
    {
        return $this->hasMany(SupplierItem::class, 'inventory_item_id');
    }

    public function purchaseRequisitionLines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class, 'inventory_item_id');
    }

    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'inventory_item_id');
    }

    public function goodsReceiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'inventory_item_id');
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'inventory_item_id');
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'inventory_item_id');
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'inventory_item_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function productionMaterialRequirements(): HasMany
    {
        return $this->hasMany(ProductionMaterialRequirement::class, 'inventory_item_id');
    }

    public function productionMaterialUsages(): HasMany
    {
        return $this->hasMany(ProductionMaterialUsage::class, 'inventory_item_id');
    }

    public function stockTransferLines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class, 'inventory_item_id');
    }

    public function stockCountLines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'inventory_item_id');
    }

    public function inventoryReconciliationLines(): HasMany
    {
        return $this->hasMany(InventoryReconciliationLine::class, 'inventory_item_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(WasteRecord::class, 'inventory_item_id');
    }
}
