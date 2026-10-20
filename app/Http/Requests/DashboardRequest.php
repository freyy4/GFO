<?php

namespace App\Http\Requests;

use App\Support\DashboardAccess;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DashboardRequest extends FormRequest
{
    public function domain(): string
    {
        return $this->route()->defaults['domain'] ?? 'overview';
    }

    public function timezone(): string
    {
        $timezone = $this->user()->organization?->timezone;

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    public function authorize(): bool
    {
        return Gate::allows('view-dashboard', $this->domain());
    }

    protected function prepareForValidation(): void
    {
        if ($this->domain() !== 'overview') {
            $today = CarbonImmutable::now($this->timezone());
            $this->mergeIfMissing(['from' => $today->subDays(29)->format('Y-m-d'), 'to' => $today->format('Y-m-d')]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:30', 'regex:/\A[A-Z0-9_ -]+\z/'],
            'page' => ['nullable', 'integer', 'min:1'], 'organization_id' => ['missing'], 'operational_unit_id' => ['missing'],
            'unit' => ['bail', 'nullable', 'integer', 'min:1', function (string $attribute, mixed $value, Closure $fail): void {
                if (! DashboardAccess::units($this->user(), $this->domain())->whereKey($value)->exists()) {
                    $fail('Unit tidak tersedia dalam akses dashboard Anda.');
                }
            }],
        ];
        if ($this->domain() !== 'overview') {
            $rules['from'] = ['required', 'date_format:Y-m-d'];
            $rules['to'] = ['required', 'date_format:Y-m-d', 'after_or_equal:from', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && is_string($this->input('from')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->input('from'))) {
                    try {
                        if (CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($value)) > 365) {
                            $fail('Rentang tanggal maksimal 366 hari.');
                        }
                    } catch (\Exception) {
                        $fail('Tanggal tidak valid.');
                    }
                }
            }];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['required' => ':attribute wajib diisi.', 'date_format' => 'Gunakan tanggal dengan format YYYY-MM-DD.',
            'after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'missing' => ':attribute ditentukan dari akun Anda.',
            'max' => ':attribute melebihi batas :max.', 'integer' => ':attribute harus berupa angka bulat.', 'regex' => 'Format status tidak valid.'];
    }
}
