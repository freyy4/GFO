<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistResponse extends Model
{
    use HasFactory;

    protected $table = 'checklist_responses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_compliant' => 'boolean',
        ];
    }

    public function checklistRun(): BelongsTo
    {
        return $this->belongsTo(ChecklistRun::class, 'checklist_run_id');
    }

    public function sopChecklistItem(): BelongsTo
    {
        return $this->belongsTo(SOPChecklistItem::class, 'sop_checklist_item_id');
    }
}
