<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QualityInspection extends Model
{
    use HasFactory;

    protected $table = 'quality_inspections';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'inspectable_id' => 'integer',
            'measurements' => 'array',
            'inspected_at' => 'datetime',
        ];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class, 'operational_unit_id');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function inspectable(): MorphTo
    {
        return $this->morphTo();
    }
}
