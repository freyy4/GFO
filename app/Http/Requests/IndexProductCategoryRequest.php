<?php

namespace App\Http\Requests;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProductCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'], 'brand_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])], 'page' => ['nullable', 'integer', 'min:1'],

        ];
    }
}
