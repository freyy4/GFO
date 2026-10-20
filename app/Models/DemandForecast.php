<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandForecast extends Model
{
    use HasFactory;

    protected $table = 'demand_forecasts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'assumptions' => 'array',
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

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function demandForecastLines(): HasMany
    {
        return $this->hasMany(DemandForecastLine::class, 'demand_forecast_id');
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'demand_forecast_id');
    }
}
