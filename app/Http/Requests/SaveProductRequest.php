<?php
namespace App\Http\Requests;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
class SaveProductRequest extends FormRequest
{
public function authorize(): bool
{
$record = $this->route('product');
return $record instanceof Product ? $this->user()->can('update', $record) : $this->user()->can('create', Product::class);
}
protected function prepareForValidation(): void { if (is_string($this->input('code'))) { $this->merge(['code' => strtoupper(trim($this->input('code')))]); } }
public function rules(): array { return [
'brand_id' => ['required', 'integer', 'min:1'],
'product_category_id' => ['nullable', 'integer', 'min:1'],
'code' => ['required', 'string', 'max:60', 'regex:/\A[A-Z0-9][A-Z0-9._-]*\z/'],
'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'],
'is_active' => ['required', 'boolean'],
'organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'deleted_at' => ['missing'],
]; }
public function messages(): array { return ['required' => ':attribute wajib diisi.', 'missing' => ':attribute ditentukan oleh sistem.', 'regex' => 'Format atau presisi :attribute tidak valid.', 'gt' => ':attribute harus lebih besar dari nol.', 'between' => ':attribute di luar batas yang diizinkan.', 'lines.list' => 'Urutan baris BOM tidak valid.', 'lines.*.array' => 'Kolom baris BOM tidak valid.']; }
}
