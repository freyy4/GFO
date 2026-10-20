<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    protected $table = 'promotions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'discount_value' => 'decimal:4',
            'minimum_spend' => 'decimal:2',
            'minimum_margin_percent' => 'decimal:4',
            'max_redemptions' => 'integer',
            'redemption_count' => 'integer',
            'rules' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function promotionTargets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class, 'promotion_id');
    }

    public function promotionRedemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class, 'promotion_id');
    }
}
