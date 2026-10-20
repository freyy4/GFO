<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyClosing extends Model
{
    use HasFactory;

    protected $table = 'daily_closings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'gross_sales' => 'decimal:2',
            'discounts' => 'decimal:2',
            'refunds' => 'decimal:2',
            'net_sales' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'non_cash_expected' => 'decimal:2',
            'non_cash_actual' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dailyClosingPayments(): HasMany
    {
        return $this->hasMany(DailyClosingPayment::class, 'daily_closing_id');
    }
}
