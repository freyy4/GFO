<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    use HasFactory;

    protected $table = 'inventory_batches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'manufactured_on' => 'date',
            'received_on' => 'date',
            'expires_on' => 'date',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'inventory_batch_id');
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'inventory_batch_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_batch_id');
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'output_inventory_batch_id');
    }

    public function productionMaterialUsages(): HasMany
    {
        return $this->hasMany(ProductionMaterialUsage::class, 'inventory_batch_id');
    }

    public function stockTransferLines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class, 'inventory_batch_id');
    }

    public function stockCountLines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'inventory_batch_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(WasteRecord::class, 'inventory_batch_id');
    }
}
