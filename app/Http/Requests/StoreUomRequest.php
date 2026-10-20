<?php

namespace App\Http\Requests;

use App\Models\UnitOfMeasure;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreUomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UnitOfMeasure::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $uom = $this->route('uom');

        return [
            'code' => ['bail', 'required', 'string', 'max:20', 'regex:/\A[A-Z0-9][A-Z0-9._\/-]*\z/', function (string $attribute, mixed $value, Closure $fail) use ($uom): void {
                if (UnitOfMeasure::whereRaw('LOWER(code) = ?', [strtolower($value)])
                    ->when($uom instanceof UnitOfMeasure, fn ($query) => $query->where('id', '!=', $uom->id))->exists()) {
                    $fail('Kode satuan sudah digunakan dalam katalog global.');
                }
            }],
            'name' => ['required', 'string', 'max:80'],
            'dimension' => ['nullable', 'string', 'max:30'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
            'organization_id' => ['missing'],
            'operational_unit_id' => ['missing'],
            'status' => ['missing'],
            'is_active' => ['missing'],
            'deleted_at' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute maksimal :max karakter.',
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, titik, garis miring, tanda hubung, dan underscore.',
            'decimal_places.integer' => 'Jumlah desimal harus berupa bilangan bulat.',
            'decimal_places.between' => 'Jumlah desimal harus 0–4, sesuai presisi kuantitas operasional.',
            'missing' => ':attribute tidak dapat ditentukan melalui formulir katalog satuan global.',
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'Kode', 'name' => 'Nama satuan', 'dimension' => 'Dimensi', 'decimal_places' => 'Jumlah desimal'];
    }
}
