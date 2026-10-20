<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationEndpoint extends Model
{
    use HasFactory;

    protected $table = 'integration_endpoints';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function integrationOutbox(): HasMany
    {
        return $this->hasMany(IntegrationOutbox::class, 'integration_endpoint_id');
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class, 'integration_endpoint_id');
    }
}
