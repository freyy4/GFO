<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CalendarEventLink extends Model
{
    use HasFactory;

    protected $table = 'calendar_event_links';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduleable_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function scheduleable(): MorphTo
    {
        return $this->morphTo();
    }
}
