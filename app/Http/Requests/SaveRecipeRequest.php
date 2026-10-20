<?php
namespace App\Http\Requests;
use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;
class SaveRecipeRequest extends FormRequest
{
public function authorize(): bool
{
$record = $this->route('recipe');
return $record instanceof Recipe ? $this->user()->can('update', $record) : $this->user()->can('create', Recipe::class);
}
protected function prepareForValidation(): void { if (is_string($this->input('code'))) { $this->merge(['code' => strtoupper(trim($this->input('code')))]); } }
public function rules(): array { return [
'brand_id' => ['required', 'integer', 'min:1'],
'product_variant_id' => ['nullable', 'integer', 'min:1'], 'output_item_id' => ['nullable', 'integer', 'min:1'],
'code' => ['required', 'string', 'max:60', 'regex:/\A[A-Z0-9][A-Z0-9._-]*\z/'],
'name' => ['required', 'string', 'max:255'], 'status' => ['required', \Illuminate\Validation\Rule::in(['ACTIVE', 'INACTIVE'])],
'organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'deleted_at' => ['missing'],
]; }
public function messages(): array { return ['required' => ':attribute wajib diisi.', 'missing' => ':attribute ditentukan oleh sistem.', 'regex' => 'Format atau presisi :attribute tidak valid.', 'gt' => ':attribute harus lebih besar dari nol.', 'between' => ':attribute di luar batas yang diizinkan.', 'lines.list' => 'Urutan baris BOM tidak valid.', 'lines.*.array' => 'Kolom baris BOM tidak valid.']; }
}
