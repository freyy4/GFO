<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_unit_id')->constrained('operational_units')->restrictOnDelete();
            $table->foreignId('destination_unit_id')->constrained('operational_units')->restrictOnDelete();
            $table->string('number', 60)->unique();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('picked_quantity', 18, 4)->default(0);
            $table->decimal('dispatched_quantity', 18, 4)->default(0);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('variance_quantity', 18, 4)->default(0);
            $table->text('variance_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->string('number', 60)->unique();
            $table->string('count_type', 30)->default('CYCLE');
            $table->dateTime('counted_at');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('physical_quantity', 18, 4);
            $table->decimal('variance_quantity', 18, 4);
            $table->decimal('variance_cost', 18, 4)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->decimal('total_variance_cost', 18, 2)->default(0);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['operational_unit_id', 'business_date'],
                'inventory_recon_unit_date_unique'
            );
        });

        Schema::create('inventory_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_reconciliation_id')
                ->constrained(indexName: 'inv_recon_lines_recon_id_fk')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_quantity', 18, 4)->default(0);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('sold_quantity', 18, 4)->default(0);
            $table->decimal('waste_quantity', 18, 4)->default(0);
            $table->decimal('expected_closing_quantity', 18, 4)->default(0);
            $table->decimal('physical_closing_quantity', 18, 4)->default(0);
            $table->decimal('variance_quantity', 18, 4)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason_code', 40)->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->dateTime('occurred_at')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('REPORTED')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_records');
        Schema::dropIfExists('inventory_reconciliation_lines');
        Schema::dropIfExists('inventory_reconciliations');
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
