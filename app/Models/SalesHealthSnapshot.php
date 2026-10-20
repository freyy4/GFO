<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesHealthSnapshot extends Model
{
    use HasFactory;

    protected $table = 'sales_health_snapshots';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'revenue' => 'decimal:2',
            'order_count' => 'integer',
            'average_order_value' => 'decimal:2',
            'units_per_transaction' => 'decimal:4',
            'gross_margin' => 'decimal:2',
            'repeat_purchase_rate' => 'decimal:4',
            'promo_dependency_rate' => 'decimal:4',
            'stockout_rate' => 'decimal:4',
            'waste_rate' => 'decimal:4',
            'customer_rating' => 'decimal:4',
            'diagnostic_data' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function growthSignals(): HasMany
    {
        return $this->hasMany(GrowthSignal::class, 'sales_health_snapshot_id');
    }
}
