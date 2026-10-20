<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->unsignedInteger('default_lead_time_hours')->default(0);
            $table->string('payment_terms', 100)->nullable();
            $table->string('approval_status', 30)->default('PENDING')->index();
            $table->decimal('performance_score', 8, 4)->nullable();
            $table->string('risk_level', 20)->default('LOW')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('supplier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('supplier_sku', 80)->nullable();
            $table->decimal('minimum_order_quantity', 18, 4)->default(0);
            $table->unsignedInteger('lead_time_hours')->default(0);
            $table->decimal('last_price', 18, 4)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'inventory_item_id']);
        });

        Schema::create('supplier_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 4);
            $table->string('currency', 3)->default('IDR');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_item_id', 'effective_from']);
        });

        Schema::create('supplier_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('contract_no', 100)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable()->index();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->text('terms')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_unit_id')->constrained('operational_units')->restrictOnDelete();
            $table->string('number', 60)->unique();
            $table->string('generation_method', 30)->default('MANUAL');
            $table->nullableMorphs('sourceable');
            $table->dateTime('required_at');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('available_quantity_snapshot', 18, 4)->default(0);
            $table->decimal('shortage_quantity', 18, 4)->default(0);
            $table->decimal('recommended_quantity', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_unit_id')->constrained('operational_units')->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60)->unique();
            $table->date('order_date');
            $table->dateTime('expected_at')->nullable()->index();
            $table->string('currency', 3)->default('IDR');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('ordered_quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_unit_id')->constrained('operational_units')->restrictOnDelete();
            $table->string('number', 60)->unique();
            $table->dateTime('received_at')->index();
            $table->string('supplier_document_no', 100)->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('qc_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->string('supplier_batch_no', 100)->nullable();
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->decimal('unit_cost', 18, 4);
            $table->string('qc_status', 30)->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requisition_lines');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('supplier_contracts');
        Schema::dropIfExists('supplier_price_histories');
        Schema::dropIfExists('supplier_items');
        Schema::dropIfExists('suppliers');
    }
};
