<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use HasFactory;

    protected $table = 'units_of_measure';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
        ];
    }

    public function uomConversionsAsFromUom(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    public function uomConversionsAsToUom(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'base_uom_id');
    }

    public function recipeVersions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'yield_uom_id');
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'uom_id');
    }

    public function supplierItems(): HasMany
    {
        return $this->hasMany(SupplierItem::class, 'purchase_uom_id');
    }

    public function purchaseRequisitionLines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class, 'uom_id');
    }

    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'uom_id');
    }

    public function goodsReceiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'uom_id');
    }

    public function productionMaterialRequirements(): HasMany
    {
        return $this->hasMany(ProductionMaterialRequirement::class, 'uom_id');
    }

    public function productionMaterialUsages(): HasMany
    {
        return $this->hasMany(ProductionMaterialUsage::class, 'uom_id');
    }

    public function stockTransferLines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class, 'uom_id');
    }
}
