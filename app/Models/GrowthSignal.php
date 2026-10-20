<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthSignal extends Model
{
    use HasFactory;

    protected $table = 'growth_signals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'drivers' => 'array',
            'confidence_score' => 'decimal:4',
            'detected_at' => 'datetime',
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

    public function salesHealthSnapshot(): BelongsTo
    {
        return $this->belongsTo(SalesHealthSnapshot::class, 'sales_health_snapshot_id');
    }

    public function marketingRecommendations(): HasMany
    {
        return $this->hasMany(MarketingRecommendation::class, 'growth_signal_id');
    }
}
