<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierItem extends Model
{
    use HasFactory;

    protected $table = 'supplier_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'minimum_order_quantity' => 'decimal:4',
            'lead_time_hours' => 'integer',
            'last_price' => 'decimal:4',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_uom_id');
    }

    public function supplierPriceHistories(): HasMany
    {
        return $this->hasMany(SupplierPriceHistory::class, 'supplier_item_id');
    }
}
