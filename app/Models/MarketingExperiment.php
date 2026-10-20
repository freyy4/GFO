<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingExperiment extends Model
{
    use HasFactory;

    protected $table = 'marketing_experiments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'control_period_start' => 'date',
            'control_period_end' => 'date',
            'treatment_period_start' => 'date',
            'treatment_period_end' => 'date',
            'expected_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'incremental_effect' => 'decimal:4',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
