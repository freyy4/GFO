<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('data_source', 50);
            $table->string('calculation_type', 30)->default('PERCENTAGE');
            $table->decimal('weight', 8, 4)->default(0);
            $table->string('direction', 20)->default('HIGHER_BETTER');
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_definition_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('targetable');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 18, 4);
            $table->timestamps();
        });

        Schema::create('kpi_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_definition_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('measurable');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4);
            $table->decimal('score', 8, 4)->nullable();
            $table->string('status', 30)->default('CALCULATED')->index();
            $table->json('calculation_detail')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_score', 8, 4);
            $table->string('rating', 30)->nullable();
            $table->json('score_breakdown');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'period_start', 'period_end'], 'employee_performance_period_unique');
        });

        Schema::create('employee_recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('reward_amount', 18, 2)->default(0);
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_recognitions');
        Schema::dropIfExists('performance_scores');
        Schema::dropIfExists('kpi_results');
        Schema::dropIfExists('kpi_targets');
        Schema::dropIfExists('kpi_definitions');
    }
};

