<?php
namespace App\Http\Requests;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
class SaveProductVariantRequest extends FormRequest
{
public function authorize(): bool
{
$record = $this->route('variant');
return $record instanceof ProductVariant ? $this->user()->can('update', $record) : $this->user()->can('create', ProductVariant::class);
}
protected function prepareForValidation(): void { if (is_string($this->input('sku'))) { $this->merge(['sku' => strtoupper(trim($this->input('sku')))]); } }
public function rules(): array { return [
'inventory_item_id' => ['nullable', 'integer', 'min:1'],
'sku' => ['required', 'string', 'max:80', 'regex:/\A[A-Z0-9][A-Z0-9._-]*\z/'],
'name' => ['required', 'string', 'max:255'],
'selling_price' => ['required', 'numeric', 'regex:/\A\d{1,16}(?:\.\d{1,2})?\z/'],
'minimum_margin_percent' => ['nullable', 'numeric', 'between:0,100', 'regex:/\A\d{1,3}(?:\.\d{1,4})?\z/'],
'is_active' => ['required', 'boolean'],
'attributes_json' => ['bail', 'nullable', 'string', 'max:10000', 'json', function (string $attribute, mixed $value, \Closure $fail): void {
$decoded = json_decode($value);
if (! $decoded instanceof \stdClass || count((array) $decoded) > 20) { $fail('Atribut harus berupa objek JSON, maksimal 20 pasangan.'); return; }
foreach ((array) $decoded as $key => $entry) { if (mb_strlen((string) $key) > 40 || ! is_string($entry) || mb_strlen($entry) > 255) { $fail('Nama atribut maksimal 40 karakter dan nilainya berupa teks maksimal 255 karakter.'); break; } }
}],
'product_id' => ['missing'], 'attributes' => ['missing'],
'organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'deleted_at' => ['missing'],
]; }
public function messages(): array { return ['required' => ':attribute wajib diisi.', 'missing' => ':attribute ditentukan oleh sistem.', 'regex' => 'Format atau presisi :attribute tidak valid.', 'gt' => ':attribute harus lebih besar dari nol.', 'between' => ':attribute di luar batas yang diizinkan.', 'lines.list' => 'Urutan baris BOM tidak valid.', 'lines.*.array' => 'Kolom baris BOM tidak valid.']; }
}
