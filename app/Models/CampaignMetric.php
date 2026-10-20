<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMetric extends Model
{
    use HasFactory;

    protected $table = 'campaign_metrics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'spend' => 'decimal:2',
            'attributed_revenue' => 'decimal:2',
            'attributed_margin' => 'decimal:2',
            'impressions' => 'integer',
            'reach' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'integer',
            'roas' => 'decimal:4',
            'cpa' => 'decimal:4',
            'extra_metrics' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
