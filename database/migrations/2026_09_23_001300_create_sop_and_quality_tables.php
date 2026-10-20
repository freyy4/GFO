<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('category', 40)->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('sop_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->longText('content')->nullable();
            $table->date('effective_from');
            $table->date('review_on')->nullable()->index();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['sop_id', 'version_no']);
        });

        Schema::create('sop_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_version_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('response_type', 30)->default('BOOLEAN');
            $table->boolean('is_required')->default(true);
            $table->decimal('minimum_value', 18, 4)->nullable();
            $table->decimal('maximum_value', 18, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('context');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 30)->default('IN_PROGRESS')->index();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('checklist_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_checklist_item_id')->constrained()->restrictOnDelete();
            $table->text('response')->nullable();
            $table->boolean('is_compliant')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['checklist_run_id', 'sop_checklist_item_id'], 'checklist_response_unique');
        });

        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('inspectable');
            $table->string('inspection_type', 40)->index();
            $table->string('result', 30)->index();
            $table->json('measurements')->nullable();
            $table->text('findings')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('inspected_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_inspections');
        Schema::dropIfExists('checklist_responses');
        Schema::dropIfExists('checklist_runs');
        Schema::dropIfExists('sop_checklist_items');
        Schema::dropIfExists('sop_versions');
        Schema::dropIfExists('sops');
    }
};

