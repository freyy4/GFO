<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PurchasingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('purchasing-action', [$this->route()->defaults['module'], $this->route('purchasing_record'), $this->route()->defaults['action']]);
    }

    public function rules(): array
    {
        return ['organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'status' => ['missing'],
            'effective_until' => [$this->route()->defaults['action'] === 'close' ? 'required' : 'missing', 'date_format:Y-m-d']];
    }
}
