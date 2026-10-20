<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReconciliationLine extends Model
{
    use HasFactory;

    protected $table = 'inventory_reconciliation_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_quantity' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'sold_quantity' => 'decimal:4',
            'waste_quantity' => 'decimal:4',
            'expected_closing_quantity' => 'decimal:4',
            'physical_closing_quantity' => 'decimal:4',
            'variance_quantity' => 'decimal:4',
        ];
    }

    public function inventoryReconciliation(): BelongsTo
    {
        return $this->belongsTo(InventoryReconciliation::class, 'inventory_reconciliation_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
