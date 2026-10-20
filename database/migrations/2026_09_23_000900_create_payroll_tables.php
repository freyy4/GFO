<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('policy_type', 40)->index();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->json('rules');
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('pay_date')->nullable();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'starts_on', 'ends_on'], 'payroll_period_unique');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('run_no')->default(1);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->decimal('gross_total', 18, 2)->default(0);
            $table->decimal('deduction_total', 18, 2)->default(0);
            $table->decimal('net_total', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'run_no']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->decimal('gross_pay', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('net_pay', 18, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component_type', 40)->index();
            $table->string('code', 60);
            $table->string('name');
            $table->decimal('amount', 18, 2);
            $table->nullableMorphs('source');
            $table->text('reason')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->decimal('allocation_percent', 8, 4)->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->decimal('allocated_amount', 18, 2)->default(0);
            $table->string('allocation_method', 30)->default('WORKED_HOURS');
            $table->timestamps();
            $table->unique(['payslip_id', 'operational_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_allocations');
        Schema::dropIfExists('payroll_components');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_policies');
    }
};

