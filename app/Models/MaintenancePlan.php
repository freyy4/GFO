<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    use HasFactory;

    protected $table = 'maintenance_plans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'interval_days' => 'integer',
            'meter_interval' => 'decimal:4',
            'next_due_date' => 'date',
            'next_meter_value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function assetReference(): BelongsTo
    {
        return $this->belongsTo(AssetReference::class, 'asset_reference_id');
    }

    public function maintenanceWorkOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'maintenance_plan_id');
    }
}
