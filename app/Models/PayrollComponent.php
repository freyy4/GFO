<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayrollComponent extends Model
{
    use HasFactory;

    protected $table = 'payroll_components';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'source_id' => 'integer',
            'is_manual' => 'boolean',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'payslip_id');
    }

    public function payrollPolicy(): BelongsTo
    {
        return $this->belongsTo(PayrollPolicy::class, 'payroll_policy_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
