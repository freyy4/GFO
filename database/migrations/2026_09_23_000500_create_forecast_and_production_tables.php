<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->string('method', 50)->default('HISTORICAL_BASELINE');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('assumptions')->nullable();
            $table->timestamps();
            $table->unique(['operational_unit_id', 'forecast_date']);
        });

        Schema::create('demand_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demand_forecast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->time('daypart_start')->nullable();
            $table->time('daypart_end')->nullable();
            $table->decimal('baseline_quantity', 18, 4);
            $table->decimal('trend_adjustment', 18, 4)->default(0);
            $table->decimal('event_adjustment', 18, 4)->default(0);
            $table->decimal('safety_percent', 8, 4)->default(0);
            $table->decimal('recommended_quantity', 18, 4);
            $table->decimal('override_quantity', 18, 4)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->constrained('operational_units')->restrictOnDelete();
            $table->foreignId('demand_forecast_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60)->unique();
            $table->date('production_date')->index();
            $table->dateTime('target_ready_at')->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_unit_id')->nullable()->constrained('operational_units')->nullOnDelete();
            $table->decimal('target_quantity', 18, 4);
            $table->decimal('planned_batches', 18, 4);
            $table->decimal('completed_quantity', 18, 4)->default(0);
            $table->string('status', 30)->default('PLANNED')->index();
            $table->timestamps();
        });

        Schema::create('production_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('available_quantity_snapshot', 18, 4)->default(0);
            $table->decimal('shortage_quantity', 18, 4)->default(0);
            $table->string('procurement_status', 30)->default('NOT_REQUIRED');
            $table->timestamps();
            $table->unique(
                ['production_plan_id', 'inventory_item_id'],
                'prod_material_plan_item_unique'
            );
        });

        Schema::create('production_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('capacity_required', 18, 4)->nullable();
            $table->decimal('capacity_available', 18, 4)->nullable();
            $table->string('status', 30)->default('PLANNED')->index();
            $table->boolean('has_conflict')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('production_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_plan_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('task_type', 40)->index();
            $table->string('name');
            $table->dateTime('planned_start_at');
            $table->dateTime('planned_end_at');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->unsignedInteger('required_crew')->default(1);
            $table->string('status', 30)->default('PENDING')->index();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_task_dependencies', function (Blueprint $table) {
            $table->foreignId('production_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('production_tasks')->cascadeOnDelete();
            $table->primary(['production_task_id', 'depends_on_task_id'], 'production_task_dependency_pk');
        });

        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipe_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_unit_id')->constrained('operational_units')->restrictOnDelete();
            $table->foreignId('output_inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('batch_no', 100)->unique();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4)->default(0);
            $table->string('status', 30)->default('PLANNED')->index();
            $table->string('qc_status', 30)->default('PENDING')->index();
            $table->timestamps();
        });

        Schema::create('production_material_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost_snapshot', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('production_qc_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->cascadeOnDelete();
            $table->string('check_type', 50);
            $table->string('result', 30)->index();
            $table->decimal('measured_value', 18, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_qc_checks');
        Schema::dropIfExists('production_material_usages');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('production_task_dependencies');
        Schema::dropIfExists('production_tasks');
        Schema::dropIfExists('production_schedules');
        Schema::dropIfExists('production_material_requirements');
        Schema::dropIfExists('production_plan_lines');
        Schema::dropIfExists('production_plans');
        Schema::dropIfExists('demand_forecast_lines');
        Schema::dropIfExists('demand_forecasts');
    }
};

