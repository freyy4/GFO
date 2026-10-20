<?php

namespace App\Http\Requests;

use App\Models\OperationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OperationalUnit::class) ?? false;
    }

    public function rules(): array
    {
        $unique = Rule::unique('operational_units', 'code')->where('organization_id', $this->user()->organization_id);
        if ($this->route('operational_unit') instanceof OperationalUnit) {
            $unique->ignore($this->route('operational_unit'));
        }

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/\A[A-Z0-9-]+\z/', $unique],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['BOOTH', 'CENTRAL_KITCHEN', 'WAREHOUSE'])],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('organization_id', $this->user()->organization_id)->whereNull('deleted_at')],
            'parent_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius_m' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'opens_at' => ['nullable', 'date_format:H:i', 'required_with:closes_at'],
            'closes_at' => ['nullable', 'date_format:H:i', 'required_with:opens_at'],
            'timezone' => ['required', 'timezone', 'max:50'],
            'settings' => ['nullable', 'json', 'max:10000'],
            'deleted_at' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'code.unique' => 'Kode sudah digunakan dalam organization ini, termasuk unit yang diarsipkan.',
            'code.regex' => 'Kode hanya boleh berisi huruf kapital, angka, dan tanda hubung.',
            'brand_id.exists' => 'Brand tidak tersedia dalam organization Anda.',
            'in' => 'Pilihan :attribute tidak valid.',
            'settings.json' => 'Pengaturan harus berupa JSON yang valid.',
            'timezone.timezone' => 'Zona waktu tidak valid.',
            'date_format' => ':attribute harus menggunakan format HH:MM.',
        ];
    }
}
