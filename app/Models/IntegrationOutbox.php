<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntegrationOutbox extends Model
{
    use HasFactory;

    protected $table = 'integration_outbox';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'attempt_count' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function integrationEndpoint(): BelongsTo
    {
        return $this->belongsTo(IntegrationEndpoint::class, 'integration_endpoint_id');
    }

    public function aggregate(): MorphTo
    {
        return $this->morphTo();
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class, 'integration_outbox_id');
    }
}
