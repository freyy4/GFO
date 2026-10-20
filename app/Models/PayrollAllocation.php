<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAllocation extends Model
{
    use HasFactory;

    protected $table = 'payroll_allocations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allocation_percent' => 'decimal:4',
            'worked_minutes' => 'integer',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'payslip_id');
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }
}
