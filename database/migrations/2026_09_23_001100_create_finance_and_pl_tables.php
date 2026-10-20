<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('pl_group', 40)->index();
            $table->boolean('is_fixed_cost')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('recurring_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('account_or_contract_no', 120)->nullable();
            $table->string('billing_cycle', 30)->default('MONTHLY');
            $table->decimal('expected_amount', 18, 2)->default(0);
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable()->index();
            $table->date('renewal_date')->nullable()->index();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_obligation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('bill_no', 100)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('usage_quantity', 18, 4)->nullable();
            $table->string('usage_unit', 30)->nullable();
            $table->decimal('amount', 18, 2);
            $table->date('due_date')->index();
            $table->date('paid_on')->nullable();
            $table->string('status', 30)->default('UNPAID')->index();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60)->unique();
            $table->date('expense_date')->index();
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 30)->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('description')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // IN, OUT
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('booth_pl_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 20); // DAILY, MONTHLY
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('revenue', 18, 2)->default(0);
            $table->decimal('cogs', 18, 2)->default(0);
            $table->decimal('gross_profit', 18, 2)->default(0);
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('rent_cost', 18, 2)->default(0);
            $table->decimal('utility_cost', 18, 2)->default(0);
            $table->decimal('security_cleaning_cost', 18, 2)->default(0);
            $table->decimal('maintenance_cost', 18, 2)->default(0);
            $table->decimal('marketing_cost', 18, 2)->default(0);
            $table->decimal('other_opex', 18, 2)->default(0);
            $table->decimal('contribution', 18, 2)->default(0);
            $table->decimal('break_even_sales', 18, 2)->nullable();
            $table->decimal('break_even_orders', 18, 4)->nullable();
            $table->json('calculation_detail')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['operational_unit_id', 'period_type', 'period_start', 'period_end'], 'booth_pl_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booth_pl_snapshots');
        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('recurring_obligations');
        Schema::dropIfExists('expense_categories');
    }
};

