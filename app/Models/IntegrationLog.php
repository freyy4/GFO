<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    use HasFactory;

    protected $table = 'integration_logs';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function integrationEndpoint(): BelongsTo
    {
        return $this->belongsTo(IntegrationEndpoint::class, 'integration_endpoint_id');
    }

    public function integrationOutbox(): BelongsTo
    {
        return $this->belongsTo(IntegrationOutbox::class, 'integration_outbox_id');
    }
}
