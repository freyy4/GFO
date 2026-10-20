<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->boolean('crosses_midnight')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('status', 30)->default('ASSIGNED')->index();
            $table->string('source', 30)->default('ROSTER');
            $table->boolean('is_replacement')->default(false);
            $table->foreignId('replaces_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'starts_at', 'ends_at'], 'employee_shift_time_idx');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type', 40)->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('PENDING')->index();
            $table->boolean('evidence_required')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->date('business_date')->index();
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->string('device_fingerprint', 191)->nullable()->index();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->boolean('geofence_valid')->nullable();
            $table->string('status', 30)->default('PENDING')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'shift_assignment_id']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->dateTime('requested_clock_in_at')->nullable();
            $table->dateTime('requested_clock_out_at')->nullable();
            $table->text('reason');
            $table->string('status', 30)->default('PENDING')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('open_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('risk_level', 20)->default('YELLOW')->index();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamp('response_deadline_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('replacement_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('ranking_score', 8, 4)->default(0);
            $table->json('score_breakdown')->nullable();
            $table->boolean('has_schedule_conflict')->default(false);
            $table->boolean('causes_overtime')->default(false);
            $table->string('status', 30)->default('SUGGESTED')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['open_shift_id', 'employee_id']);
        });

        Schema::create('shift_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('PENDING')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->unique(['open_shift_id', 'employee_id']);
        });

        Schema::create('shift_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_assignment_id')->constrained('shift_assignments')->restrictOnDelete();
            $table->foreignId('to_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('to_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->string('status', 30)->default('PENDING')->index();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swaps');
        Schema::dropIfExists('shift_claims');
        Schema::dropIfExists('replacement_candidates');
        Schema::dropIfExists('open_shifts');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('rosters');
        Schema::dropIfExists('shift_templates');
    }
};

