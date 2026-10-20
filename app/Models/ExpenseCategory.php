<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'expense_categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_fixed_cost' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function recurringObligations(): HasMany
    {
        return $this->hasMany(RecurringObligation::class, 'expense_category_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'expense_category_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }
}
