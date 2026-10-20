<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingRecommendation extends Model
{
    use HasFactory;

    protected $table = 'marketing_recommendations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'guardrail_checks' => 'array',
        ];
    }

    public function growthSignal(): BelongsTo
    {
        return $this->belongsTo(GrowthSignal::class, 'growth_signal_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'marketing_recommendation_id');
    }
}
