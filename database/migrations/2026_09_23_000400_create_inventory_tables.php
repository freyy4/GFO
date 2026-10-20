<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_no', 100);
            $table->date('manufactured_on')->nullable();
            $table->date('received_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->string('qc_status', 30)->default('RELEASED')->index();
            $table->timestamps();
            $table->unique(['inventory_item_id', 'batch_no']);
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('on_hand', 18, 4)->default(0);
            $table->decimal('reserved', 18, 4)->default(0);
            $table->decimal('in_production', 18, 4)->default(0);
            $table->decimal('in_transit', 18, 4)->default(0);
            $table->decimal('quarantined', 18, 4)->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();
            $table->unique(['operational_unit_id', 'inventory_item_id', 'inventory_batch_id'], 'inventory_balance_unique');
            $table->index(['operational_unit_id', 'inventory_item_id']);
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('reservable');
            $table->decimal('quantity', 18, 4);
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('movement_type', 40)->index();
            $table->decimal('quantity', 18, 4); // signed quantity
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at')->index();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['operational_unit_id', 'inventory_item_id', 'occurred_at'], 'inventory_ledger_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_batches');
    }
};

