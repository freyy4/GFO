<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-profile');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/\A[0-9+().\s-]+\z/'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'current_password' => ['required', 'string', 'current_password:web'],
            ...self::protectedFields(),
        ];
    }

    public static function protectedFields(): array
    {
        return array_fill_keys(['id', 'user_id', 'organization_id', 'operational_unit_id', 'role_id', 'roles', 'status', 'mfa_enabled', 'email_verified_at', 'remember_token', 'deleted_at', 'last_login_at'], ['missing']);
    }
}
