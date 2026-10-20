<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandForecastLine extends Model
{
    use HasFactory;

    protected $table = 'demand_forecast_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'baseline_quantity' => 'decimal:4',
            'trend_adjustment' => 'decimal:4',
            'event_adjustment' => 'decimal:4',
            'safety_percent' => 'decimal:4',
            'recommended_quantity' => 'decimal:4',
            'override_quantity' => 'decimal:4',
        ];
    }

    public function demandForecast(): BelongsTo
    {
        return $this->belongsTo(DemandForecast::class, 'demand_forecast_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
