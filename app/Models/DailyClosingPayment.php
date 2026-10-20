<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosingPayment extends Model
{
    use HasFactory;

    protected $table = 'daily_closing_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'variance_amount' => 'decimal:2',
        ];
    }

    public function dailyClosing(): BelongsTo
    {
        return $this->belongsTo(DailyClosing::class, 'daily_closing_id');
    }
}
