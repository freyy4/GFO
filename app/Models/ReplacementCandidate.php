<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplacementCandidate extends Model
{
    use HasFactory;

    protected $table = 'replacement_candidates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ranking_score' => 'decimal:4',
            'score_breakdown' => 'array',
            'has_schedule_conflict' => 'boolean',
            'causes_overtime' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function openShift(): BelongsTo
    {
        return $this->belongsTo(OpenShift::class, 'open_shift_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
