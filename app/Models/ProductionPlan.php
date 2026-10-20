<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionPlan extends Model
{
    use HasFactory;

    protected $table = 'production_plans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'target_ready_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'production_unit_id');
    }

    public function demandForecast(): BelongsTo
    {
        return $this->belongsTo(DemandForecast::class, 'demand_forecast_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function productionPlanLines(): HasMany
    {
        return $this->hasMany(ProductionPlanLine::class, 'production_plan_id');
    }

    public function productionMaterialRequirements(): HasMany
    {
        return $this->hasMany(ProductionMaterialRequirement::class, 'production_plan_id');
    }

    public function productionSchedules(): HasMany
    {
        return $this->hasMany(ProductionSchedule::class, 'production_plan_id');
    }
}
