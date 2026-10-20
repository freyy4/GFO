<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'campaigns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_budget' => 'decimal:2',
            'actual_spend' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function marketingRecommendation(): BelongsTo
    {
        return $this->belongsTo(MarketingRecommendation::class, 'marketing_recommendation_id');
    }

    public function marketingBudget(): BelongsTo
    {
        return $this->belongsTo(MarketingBudget::class, 'marketing_budget_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function campaignTargets(): HasMany
    {
        return $this->hasMany(CampaignTarget::class, 'campaign_id');
    }

    public function contentCalendarItems(): HasMany
    {
        return $this->hasMany(ContentCalendarItem::class, 'campaign_id');
    }

    public function kolEngagements(): HasMany
    {
        return $this->hasMany(KOLEngagement::class, 'campaign_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'campaign_id');
    }

    public function productBundles(): HasMany
    {
        return $this->hasMany(ProductBundle::class, 'campaign_id');
    }

    public function marketingExperiments(): HasMany
    {
        return $this->hasMany(MarketingExperiment::class, 'campaign_id');
    }

    public function campaignMetrics(): HasMany
    {
        return $this->hasMany(CampaignMetric::class, 'campaign_id');
    }

    public function creativeAssets(): BelongsToMany
    {
        return $this->belongsToMany(CreativeAsset::class, 'campaign_creatives');
    }
}
