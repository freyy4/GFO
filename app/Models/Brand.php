<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'brands';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function operationalUnits(): HasMany
    {
        return $this->hasMany(OperationalUnit::class, 'brand_id');
    }

    public function appSettings(): HasMany
    {
        return $this->hasMany(AppSetting::class, 'brand_id');
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'brand_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'brand_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'brand_id');
    }

    public function demandForecasts(): HasMany
    {
        return $this->hasMany(DemandForecast::class, 'brand_id');
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'brand_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'brand_id');
    }

    public function sops(): HasMany
    {
        return $this->hasMany(SOP::class, 'brand_id');
    }

    public function salesHealthSnapshots(): HasMany
    {
        return $this->hasMany(SalesHealthSnapshot::class, 'brand_id');
    }

    public function growthSignals(): HasMany
    {
        return $this->hasMany(GrowthSignal::class, 'brand_id');
    }

    public function marketingBudgets(): HasMany
    {
        return $this->hasMany(MarketingBudget::class, 'brand_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'brand_id');
    }

    public function creativeAssets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class, 'brand_id');
    }

    public function contentCalendarItems(): HasMany
    {
        return $this->hasMany(ContentCalendarItem::class, 'brand_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'brand_id');
    }

    public function productBundles(): HasMany
    {
        return $this->hasMany(ProductBundle::class, 'brand_id');
    }
}
