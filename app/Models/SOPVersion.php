<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SOPVersion extends Model
{
    use HasFactory;

    protected $table = 'sop_versions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'effective_from' => 'date',
            'review_on' => 'date',
        ];
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(SOP::class, 'sop_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sopChecklistItems(): HasMany
    {
        return $this->hasMany(SOPChecklistItem::class, 'sop_version_id');
    }

    public function checklistRuns(): HasMany
    {
        return $this->hasMany(ChecklistRun::class, 'sop_version_id');
    }
}
