<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KOLEngagement extends Model
{
    use HasFactory;

    protected $table = 'kol_engagements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'deliverables' => 'array',
            'attributed_sales' => 'decimal:2',
            'result_metrics' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function kolProfile(): BelongsTo
    {
        return $this->belongsTo(KOLProfile::class, 'kol_profile_id');
    }
}
