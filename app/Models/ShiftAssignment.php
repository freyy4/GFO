<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftAssignment extends Model
{
    use HasFactory;

    protected $table = 'shift_assignments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_replacement' => 'boolean',
        ];
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class, 'roster_id');
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function replacesAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'replaces_assignment_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'replaces_assignment_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'shift_assignment_id');
    }

    public function openShifts(): HasMany
    {
        return $this->hasMany(OpenShift::class, 'shift_assignment_id');
    }

    public function shiftSwapsAsFromAssignment(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'from_assignment_id');
    }

    public function shiftSwapsAsToAssignment(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'to_assignment_id');
    }
}
