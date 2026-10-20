<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesChannel extends Model
{
    use HasFactory;

    protected $table = 'sales_channels';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'sales_channel_id');
    }

    public function paymentSettlements(): HasMany
    {
        return $this->hasMany(PaymentSettlement::class, 'sales_channel_id');
    }
}
