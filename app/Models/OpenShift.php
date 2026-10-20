<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenShift extends Model
{
    use HasFactory;

    protected $table = 'open_shifts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'response_deadline_at' => 'datetime',
        ];
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replacementCandidates(): HasMany
    {
        return $this->hasMany(ReplacementCandidate::class, 'open_shift_id');
    }

    public function shiftClaims(): HasMany
    {
        return $this->hasMany(ShiftClaim::class, 'open_shift_id');
    }
}
