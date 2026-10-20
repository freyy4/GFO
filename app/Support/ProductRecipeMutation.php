<?php

namespace App\Support;

use App\Models\{AuditLog, Organization, ProductVariant, RecipeVersion};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductRecipeMutation
{
    public static function lock(Request $request): void
    {
        Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
        CatalogAccess::forget($request->user());
    }

    public static function audit(Request $request, Model $record, string $event, array $before = [], ?array $after = null): void
    {
        AuditLog::create(['organization_id' => $request->user()->organization_id, 'user_id' => $request->user()->id,
            'event' => $event, 'auditable_type' => $record->getMorphClass(), 'auditable_id' => $record->id,
            'old_values' => $before, 'new_values' => $after ?? $record->toArray(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }

    public static function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public static function variantUsed(ProductVariant $variant): bool
    {
        foreach (['unit_product_prices', 'recipes', 'demand_forecast_lines', 'sales_order_items', 'sales_health_snapshots', 'growth_signals', 'campaign_targets', 'content_calendar_items', 'promotion_targets', 'product_bundle_items'] as $table) {
            if (DB::table($table)->where('product_variant_id', $variant->id)->exists()) {
                return true;
            }
        }
        return false;
    }

    public static function versionUsed(RecipeVersion $version): bool
    {
        foreach (['recipe_lines' => 'sub_recipe_version_id', 'recipe_cost_snapshots' => 'recipe_version_id', 'production_plan_lines' => 'recipe_version_id', 'production_batches' => 'recipe_version_id'] as $table => $column) {
            if (DB::table($table)->where($column, $version->id)->exists()) {
                return true;
            }
        }
        return false;
    }

    public static function snapshot(RecipeVersion $version): array
    {
        return array_merge($version->attributesToArray(), ['lines' => $version->recipeLinesAsRecipeVersion()->orderBy('sort_order')->get()->toArray()]);
    }
}
