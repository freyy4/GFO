<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CaseEvidence extends Model
{
    use HasFactory;

    protected $table = 'case_evidence';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function reviewCase(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class, 'review_case_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
