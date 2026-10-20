<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KPITarget extends Model
{
    use HasFactory;

    protected $table = 'kpi_targets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'targetable_id' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'target_value' => 'decimal:4',
        ];
    }

    public function kpiDefinition(): BelongsTo
    {
        return $this->belongsTo(KPIDefinition::class, 'kpi_definition_id');
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
