<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', InventoryItem::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'], 'brand_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE', 'ARCHIVED'])], 'page' => ['nullable', 'integer', 'min:1'],
            'item_type' => ['nullable', Rule::in(array_keys(StoreInventoryItemRequest::TYPES))],
        ];
    }
}
