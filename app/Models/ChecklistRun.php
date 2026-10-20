<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChecklistRun extends Model
{
    use HasFactory;

    protected $table = 'checklist_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'context_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sopVersion(): BelongsTo
    {
        return $this->belongsTo(SOPVersion::class, 'sop_version_id');
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function checklistResponses(): HasMany
    {
        return $this->hasMany(ChecklistResponse::class, 'checklist_run_id');
    }
}
