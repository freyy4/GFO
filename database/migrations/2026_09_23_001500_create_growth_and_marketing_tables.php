<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('health_status', 30)->index();
            $table->decimal('revenue', 18, 2)->default(0);
            $table->unsignedBigInteger('order_count')->default(0);
            $table->decimal('average_order_value', 18, 2)->default(0);
            $table->decimal('units_per_transaction', 18, 4)->default(0);
            $table->decimal('gross_margin', 18, 2)->default(0);
            $table->decimal('repeat_purchase_rate', 8, 4)->nullable();
            $table->decimal('promo_dependency_rate', 8, 4)->nullable();
            $table->decimal('stockout_rate', 8, 4)->nullable();
            $table->decimal('waste_rate', 8, 4)->nullable();
            $table->decimal('customer_rating', 8, 4)->nullable();
            $table->json('diagnostic_data')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->index(['brand_id', 'period_start', 'period_end'], 'sales_health_period_idx');
        });

        Schema::create('growth_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_health_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('signal_type', 60)->index();
            $table->string('direction', 20)->index();
            $table->string('severity', 20)->default('MEDIUM')->index();
            $table->text('summary');
            $table->json('drivers')->nullable();
            $table->decimal('confidence_score', 8, 4)->nullable();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamp('detected_at');
            $table->timestamps();
        });

        Schema::create('marketing_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growth_signal_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 60)->index();
            $table->text('diagnosis');
            $table->text('proposed_action');
            $table->string('objective', 40);
            $table->decimal('budget_min', 18, 2)->nullable();
            $table->decimal('budget_max', 18, 2)->nullable();
            $table->json('guardrail_checks')->nullable();
            $table->string('status', 30)->default('PROPOSED')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 40)->nullable()->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('allocated_amount', 18, 2);
            $table->decimal('committed_amount', 18, 2)->default(0);
            $table->decimal('spent_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_recommendation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketing_budget_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('campaign_type', 40)->index();
            $table->string('channel', 40)->index();
            $table->string('objective', 40);
            $table->text('hypothesis')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('approved_budget', 18, 2)->default(0);
            $table->decimal('actual_spend', 18, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('customer_segment')->nullable();
            $table->json('targeting')->nullable();
            $table->timestamps();
        });

        Schema::create('creative_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('asset_type', 40);
            $table->string('file_path', 1000)->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_creatives', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creative_asset_id')->constrained()->cascadeOnDelete();
            $table->primary(['campaign_id', 'creative_asset_id']);
        });

        Schema::create('content_calendar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 40)->index();
            $table->string('title');
            $table->text('caption')->nullable();
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('published_at')->nullable();
            $table->string('external_post_id', 150)->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kol_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 40)->nullable();
            $table->string('handle', 120)->nullable();
            $table->string('audience_area')->nullable();
            $table->unsignedBigInteger('follower_count')->nullable();
            $table->decimal('default_rate', 18, 2)->nullable();
            $table->json('profile_metrics')->nullable();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('kol_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kol_profile_id')->constrained()->restrictOnDelete();
            $table->decimal('fee', 18, 2)->default(0);
            $table->json('deliverables')->nullable();
            $table->string('promo_code', 80)->nullable()->index();
            $table->string('attribution_url', 1000)->nullable();
            $table->decimal('attributed_sales', 18, 2)->default(0);
            $table->json('result_metrics')->nullable();
            $table->string('status', 30)->default('PLANNED')->index();
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 80)->nullable()->unique();
            $table->string('name');
            $table->string('promotion_type', 40)->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('discount_value', 18, 4)->nullable();
            $table->decimal('minimum_spend', 18, 2)->default(0);
            $table->decimal('minimum_margin_percent', 8, 4)->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->json('rules')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('customer_segment')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 18, 2);
            $table->timestamp('redeemed_at')->index();
            $table->timestamps();
            $table->unique(['promotion_id', 'sales_order_id']);
        });

        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->decimal('bundle_price', 18, 2);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->timestamps();
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->timestamps();
            $table->unique(['product_bundle_id', 'product_variant_id']);
        });

        Schema::create('marketing_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('hypothesis');
            $table->date('control_period_start')->nullable();
            $table->date('control_period_end')->nullable();
            $table->date('treatment_period_start');
            $table->date('treatment_period_end');
            $table->string('primary_kpi', 60);
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('incremental_effect', 18, 4)->nullable();
            $table->string('decision', 30)->nullable();
            $table->string('status', 30)->default('PLANNED')->index();
            $table->timestamps();
        });

        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->decimal('spend', 18, 2)->default(0);
            $table->decimal('attributed_revenue', 18, 2)->default(0);
            $table->decimal('attributed_margin', 18, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('roas', 18, 4)->nullable();
            $table->decimal('cpa', 18, 4)->nullable();
            $table->json('extra_metrics')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('marketing_experiments');
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('kol_engagements');
        Schema::dropIfExists('kol_profiles');
        Schema::dropIfExists('content_calendar_items');
        Schema::dropIfExists('campaign_creatives');
        Schema::dropIfExists('creative_assets');
        Schema::dropIfExists('campaign_targets');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('marketing_budgets');
        Schema::dropIfExists('marketing_recommendations');
        Schema::dropIfExists('growth_signals');
        Schema::dropIfExists('sales_health_snapshots');
    }
};

