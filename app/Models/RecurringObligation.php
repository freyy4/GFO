<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringObligation extends Model
{
    use HasFactory;

    protected $table = 'recurring_obligations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'due_day' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'renewal_date' => 'date',
            'details' => 'array',
        ];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'recurring_obligation_id');
    }
}
