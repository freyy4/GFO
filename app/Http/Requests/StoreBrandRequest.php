<?php

namespace App\Http\Requests;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['organization_id' => $this->user()?->organization_id]);
    }

    public function rules(): array
    {
        $unique = Rule::unique('brands', 'code')->where('organization_id', $this->user()?->organization_id);
        if ($this->route('brand') instanceof Brand) {
            $unique->ignore($this->route('brand'));
        }

        return [
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9-]+\z/', $unique],
            'name' => ['required', 'string', 'max:255'],
            'business_model' => ['required', 'string', 'max:50'],
            'inventory_policy' => ['required', Rule::in(['FEFO', 'FIFO'])],
            'costing_method' => ['required', 'string', 'max:40'],
            'settings' => ['nullable', 'json'],
            'status' => ['missing'],
            'activated_at' => ['missing'],
            'deleted_at' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute maksimal :max karakter.',
            'code.regex' => 'Kode hanya boleh berisi huruf kapital, angka, dan tanda hubung.',
            'code.unique' => 'Kode sudah digunakan dalam organization ini, termasuk brand yang diarsipkan.',
            'organization_id.exists' => 'Organization tidak tersedia.',
            'inventory_policy.in' => 'Pilih FEFO atau FIFO.',
            'settings.json' => 'Pengaturan tambahan harus berupa JSON yang valid.',
            'missing' => ':attribute hanya dapat diubah melalui aksi khusus.',
        ];
    }

    public function attributes(): array
    {
        return ['organization_id' => 'Organization', 'code' => 'Kode', 'name' => 'Nama brand',
            'business_model' => 'Business model', 'inventory_policy' => 'Inventory policy',
            'costing_method' => 'Costing method', 'settings' => 'Pengaturan tambahan',
            'status' => 'Status', 'activated_at' => 'Tanggal aktivasi', 'deleted_at' => 'Tanggal penghapusan'];
    }
}
