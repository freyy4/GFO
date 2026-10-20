<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;

class UpdateInventoryItemRequest extends StoreInventoryItemRequest
{
    public function authorize(): bool
    {
        $record = $this->route('inventory_item');

        return $record instanceof InventoryItem && ($this->user()?->can('update', $record) ?? false);
    }
}
