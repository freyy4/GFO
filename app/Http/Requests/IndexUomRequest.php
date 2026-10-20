<?php

namespace App\Http\Requests;

use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', UnitOfMeasure::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'dimension' => ['nullable', 'string', 'max:30'],
            'usage' => ['nullable', Rule::in(['used', 'unused'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
