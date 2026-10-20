<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use HasFactory;

    protected $table = 'inventory_balances';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:4',
            'reserved' => 'decimal:4',
            'in_production' => 'decimal:4',
            'in_transit' => 'decimal:4',
            'quarantined' => 'decimal:4',
            'last_counted_at' => 'datetime',
        ];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }
}
