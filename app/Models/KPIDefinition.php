<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KPIDefinition extends Model
{
    use HasFactory;

    protected $table = 'kpi_definitions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function kpiTargets(): HasMany
    {
        return $this->hasMany(KPITarget::class, 'kpi_definition_id');
    }

    public function kpiResults(): HasMany
    {
        return $this->hasMany(KPIResult::class, 'kpi_definition_id');
    }
}
