<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('domain', 40)->index();
            $table->string('severity', 20)->default('MEDIUM');
            $table->json('conditions');
            $table->json('notification_channels')->nullable();
            $table->unsignedInteger('cooldown_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain', 40)->index();
            $table->string('type', 60)->index();
            $table->string('severity', 20)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->nullableMorphs('subject');
            $table->decimal('risk_score', 8, 4)->nullable();
            $table->json('evidence_snapshot')->nullable();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamp('detected_at')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('review_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60)->unique();
            $table->string('case_type', 50)->index();
            $table->string('severity', 20)->default('MEDIUM')->index();
            $table->string('status', 30)->default('OPEN')->index();
            $table->text('summary');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('case_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_case_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->string('evidence_type', 40);
            $table->text('description')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('case_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_case_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 40)->index();
            $table->text('reason');
            $table->date('effective_on')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        Schema::create('case_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('statement');
            $table->string('status', 30)->default('SUBMITTED')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_appeals');
        Schema::dropIfExists('case_actions');
        Schema::dropIfExists('case_evidence');
        Schema::dropIfExists('review_cases');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
    }
};

