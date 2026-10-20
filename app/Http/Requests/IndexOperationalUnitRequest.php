<?php

namespace App\Http\Requests;

use App\Models\OperationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOperationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', OperationalUnit::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
            'type' => ['nullable', Rule::in(['BOOTH', 'CENTRAL_KITCHEN', 'WAREHOUSE'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
