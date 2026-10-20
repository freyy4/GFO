<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KOLProfile extends Model
{
    use HasFactory;

    protected $table = 'kol_profiles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'follower_count' => 'integer',
            'default_rate' => 'decimal:2',
            'profile_metrics' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function kolEngagements(): HasMany
    {
        return $this->hasMany(KOLEngagement::class, 'kol_profile_id');
    }
}
