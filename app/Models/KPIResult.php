<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KPIResult extends Model
{
    use HasFactory;

    protected $table = 'kpi_results';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'measurable_id' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'target_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'score' => 'decimal:4',
            'calculation_detail' => 'array',
        ];
    }

    public function kpiDefinition(): BelongsTo
    {
        return $this->belongsTo(KPIDefinition::class, 'kpi_definition_id');
    }

    public function measurable(): MorphTo
    {
        return $this->morphTo();
    }
}
