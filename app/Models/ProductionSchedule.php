<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionSchedule extends Model
{
    use HasFactory;

    protected $table = 'production_schedules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity_required' => 'decimal:4',
            'capacity_available' => 'decimal:4',
            'has_conflict' => 'boolean',
        ];
    }

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class, 'production_schedule_id');
    }
}
