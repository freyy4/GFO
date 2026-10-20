<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_variants';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'minimum_margin_percent' => 'decimal:4',
            'is_active' => 'boolean',
            'attributes' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function unitProductPrices(): HasMany
    {
        return $this->hasMany(UnitProductPrice::class, 'product_variant_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'product_variant_id');
    }

    public function demandForecastLines(): HasMany
    {
        return $this->hasMany(DemandForecastLine::class, 'product_variant_id');
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'product_variant_id');
    }

    public function salesHealthSnapshots(): HasMany
    {
        return $this->hasMany(SalesHealthSnapshot::class, 'product_variant_id');
    }

    public function growthSignals(): HasMany
    {
        return $this->hasMany(GrowthSignal::class, 'product_variant_id');
    }

    public function campaignTargets(): HasMany
    {
        return $this->hasMany(CampaignTarget::class, 'product_variant_id');
    }

    public function contentCalendarItems(): HasMany
    {
        return $this->hasMany(ContentCalendarItem::class, 'product_variant_id');
    }

    public function promotionTargets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class, 'product_variant_id');
    }

    public function productBundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'product_variant_id');
    }
}
