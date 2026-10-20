<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SOPChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'sop_checklist_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'minimum_value' => 'decimal:4',
            'maximum_value' => 'decimal:4',
            'sort_order' => 'integer',
            'options' => 'array',
        ];
    }

    public function sopVersion(): BelongsTo
    {
        return $this->belongsTo(SOPVersion::class, 'sop_version_id');
    }

    public function checklistResponses(): HasMany
    {
        return $this->hasMany(ChecklistResponse::class, 'sop_checklist_item_id');
    }
}
