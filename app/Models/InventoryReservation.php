<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryReservation extends Model
{
    use HasFactory;

    protected $table = 'inventory_reservations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reservable_id' => 'integer',
            'quantity' => 'decimal:4',
            'expires_at' => 'datetime',
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

    public function reservable(): MorphTo
    {
        return $this->morphTo();
    }
}
