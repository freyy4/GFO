<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoothPLSnapshot extends Model
{
    use HasFactory;

    protected $table = 'booth_pl_snapshots';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'revenue' => 'decimal:2',
            'cogs' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'rent_cost' => 'decimal:2',
            'utility_cost' => 'decimal:2',
            'security_cleaning_cost' => 'decimal:2',
            'maintenance_cost' => 'decimal:2',
            'marketing_cost' => 'decimal:2',
            'other_opex' => 'decimal:2',
            'contribution' => 'decimal:2',
            'break_even_sales' => 'decimal:2',
            'break_even_orders' => 'decimal:4',
            'calculation_detail' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }
}
