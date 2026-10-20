<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAction extends Model
{
    use HasFactory;

    protected $table = 'case_actions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function reviewCase(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class, 'review_case_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
