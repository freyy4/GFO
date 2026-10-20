<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_asset_id', 100)->unique();
            $table->string('asset_code', 80)->nullable()->index();
            $table->string('name');
            $table->string('category', 50)->nullable();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('external_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_reference_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type', 30)->default('TIME');
            $table->unsignedInteger('interval_days')->nullable();
            $table->decimal('meter_interval', 18, 4)->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->decimal('next_meter_value', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_reference_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60)->unique();
            $table->string('type', 30)->default('PREVENTIVE');
            $table->string('priority', 20)->default('NORMAL')->index();
            $table->text('issue_description')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('cost', 18, 2)->default(0);
            $table->date('next_maintenance_date')->nullable();
            $table->string('status', 30)->default('OPEN')->index();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_orders');
        Schema::dropIfExists('maintenance_plans');
        Schema::dropIfExists('asset_references');
    }
};

