<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('dimension', 30)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->timestamps();
        });

        Schema::create('uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->foreignId('to_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('factor', 18, 8);
            $table->timestamps();
            $table->unique(['from_uom_id', 'to_uom_id']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['brand_id', 'slug']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('name');
            $table->string('item_type', 30)->index();
            $table->string('tracking_policy', 20)->default('NONE'); // NONE, LOT, LOT_EXPIRY
            $table->string('issue_policy', 20)->default('FIFO');
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('safety_stock', 18, 4)->default(0);
            $table->decimal('standard_cost', 18, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'sku']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['brand_id', 'code']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('name');
            $table->decimal('selling_price', 18, 2);
            $table->decimal('minimum_margin_percent', 8, 4)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unit_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['operational_unit_id', 'product_variant_id', 'is_active'], 'unit_product_price_idx');
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('output_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['brand_id', 'code']);
        });

        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->decimal('yield_quantity', 18, 4);
            $table->foreignId('yield_uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->decimal('calculated_cost', 18, 4)->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['recipe_id', 'version_no']);
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sub_recipe_version_id')->nullable()->constrained('recipe_versions')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('waste_percent', 8, 4)->default(0);
            $table->decimal('cost_snapshot', 18, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('recipe_version_id');
        });

        Schema::create('recipe_cost_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained()->cascadeOnDelete();
            $table->date('cost_date');
            $table->decimal('ingredient_cost', 18, 4);
            $table->decimal('packaging_cost', 18, 4)->default(0);
            $table->decimal('labor_cost', 18, 4)->default(0);
            $table->decimal('overhead_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4);
            $table->json('calculation_detail')->nullable();
            $table->timestamps();
            $table->unique(['recipe_version_id', 'cost_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_cost_snapshots');
        Schema::dropIfExists('recipe_lines');
        Schema::dropIfExists('recipe_versions');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('unit_product_prices');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('uom_conversions');
        Schema::dropIfExists('units_of_measure');
    }
};

