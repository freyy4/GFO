<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_requisition_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:4',
            'available_quantity_snapshot' => 'decimal:4',
            'shortage_quantity' => 'decimal:4',
            'recommended_quantity' => 'decimal:4',
        ];
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
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
