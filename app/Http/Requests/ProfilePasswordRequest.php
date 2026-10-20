<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class ProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-profile');
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'max:72', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Password maksimal 72 byte.');
                }
            }, 'different:current_password', Password::min(12)->mixedCase()->numbers()->symbols()],
            ...ProfileUpdateRequest::protectedFields()];
    }
}
