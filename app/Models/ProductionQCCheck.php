<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQCCheck extends Model
{
    use HasFactory;

    protected $table = 'production_qc_checks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'measured_value' => 'decimal:4',
            'checked_at' => 'datetime',
        ];
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
