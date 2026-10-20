<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('system_code', 50)->index();
            $table->string('direction', 20)->default('OUTBOUND');
            $table->string('base_url', 1000)->nullable();
            $table->string('auth_type', 30)->default('NONE');
            $table->text('encrypted_credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_endpoint_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->nullableMorphs('aggregate');
            $table->json('payload');
            $table->string('status', 30)->default('PENDING')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_outbox_id')->nullable()->constrained('integration_outbox')->nullOnDelete();
            $table->string('direction', 20);
            $table->string('method', 10)->nullable();
            $table->string('url', 1000)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 30)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('calendar_event_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('scheduleable');
            $table->string('provider', 30)->default('GOOGLE');
            $table->string('external_calendar_id', 255);
            $table->string('external_event_id', 255);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 30)->default('SYNCED')->index();
            $table->timestamps();
            $table->unique(['provider', 'external_calendar_id', 'external_event_id'], 'calendar_external_event_unique');
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('channel', 30);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('notifiable');
            $table->nullableMorphs('reference');
            $table->string('channel', 30)->index();
            $table->string('destination');
            $table->text('rendered_body');
            $table->string('status', 30)->default('QUEUED')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('calendar_event_links');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_outbox');
        Schema::dropIfExists('integration_endpoints');
    }
};

