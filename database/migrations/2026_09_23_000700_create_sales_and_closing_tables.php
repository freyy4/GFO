<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('type', 30); // POS, DELIVERY, MARKETPLACE
            $table->decimal('commission_percent', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->json('consent')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['operational_unit_id', 'code']);
        });

        Schema::create('register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_cash', 18, 2)->default(0);
            $table->decimal('closing_cash', 18, 2)->nullable();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamps();
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('register_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 80)->unique();
            $table->string('external_order_id', 100)->nullable()->index();
            $table->dateTime('ordered_at')->index();
            $table->string('status', 30)->default('COMPLETED')->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('service_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('cogs_amount', 18, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['operational_unit_id', 'ordered_at', 'status'], 'sales_order_reporting_idx');
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('item_name_snapshot');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->decimal('unit_cogs', 18, 4)->default(0);
            $table->json('modifiers')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30)->index();
            $table->string('provider')->nullable();
            $table->string('reference_no', 120)->nullable()->index();
            $table->decimal('amount', 18, 2);
            $table->string('status', 30)->default('PAID')->index();
            $table->dateTime('paid_at');
            $table->json('gateway_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->string('number', 80)->unique();
            $table->decimal('amount', 18, 2);
            $table->text('reason');
            $table->string('status', 30)->default('PENDING')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 30)->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('expected_amount', 18, 2)->default(0);
            $table->decimal('settled_amount', 18, 2)->default(0);
            $table->decimal('fee_amount', 18, 2)->default(0);
            $table->decimal('variance_amount', 18, 2)->default(0);
            $table->string('reference_no', 120)->nullable();
            $table->string('status', 30)->default('PENDING')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->decimal('gross_sales', 18, 2)->default(0);
            $table->decimal('discounts', 18, 2)->default(0);
            $table->decimal('refunds', 18, 2)->default(0);
            $table->decimal('net_sales', 18, 2)->default(0);
            $table->decimal('expected_cash', 18, 2)->default(0);
            $table->decimal('actual_cash', 18, 2)->default(0);
            $table->decimal('cash_variance', 18, 2)->default(0);
            $table->decimal('non_cash_expected', 18, 2)->default(0);
            $table->decimal('non_cash_actual', 18, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('variance_reason')->nullable();
            $table->timestamps();
            $table->unique(['operational_unit_id', 'business_date']);
        });

        Schema::create('daily_closing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_closing_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 30);
            $table->decimal('expected_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->decimal('variance_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['daily_closing_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closing_payments');
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('payment_settlements');
        Schema::dropIfExists('sales_refunds');
        Schema::dropIfExists('sales_payments');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('register_sessions');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('sales_channels');
    }
};

