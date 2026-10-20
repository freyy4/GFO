<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'supplier_price_histories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function supplierItem(): BelongsTo
    {
        return $this->belongsTo(SupplierItem::class, 'supplier_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
