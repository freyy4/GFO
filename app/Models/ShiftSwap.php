<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwap extends Model
{
    use HasFactory;

    protected $table = 'shift_swaps';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
        ];
    }

    public function fromAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'from_assignment_id');
    }

    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    public function toAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'to_assignment_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
