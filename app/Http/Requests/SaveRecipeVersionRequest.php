<?php
namespace App\Http\Requests;
use App\Models\RecipeVersion;
use Illuminate\Foundation\Http\FormRequest;
class SaveRecipeVersionRequest extends FormRequest
{
public function authorize(): bool
{
$record = $this->route('version');
return $record instanceof RecipeVersion ? $this->user()->can('update', $record) : $this->user()->can('create', RecipeVersion::class);
}
public function rules(): array { return [
'yield_quantity' => ['required', 'numeric', 'gt:0', 'regex:/\A\d{1,14}(?:\.\d{1,4})?\z/'],
'yield_uom_id' => ['required', 'integer', 'min:1'],
'effective_from' => ['required', 'date_format:Y-m-d'],
'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
'notes' => ['nullable', 'string', 'max:10000'],
'lines' => ['required', 'array', 'min:1', 'max:100', 'list'],
'lines.*' => ['required', 'array:inventory_item_id,sub_recipe_version_id,uom_id,quantity,waste_percent'],
'lines.*.inventory_item_id' => ['nullable', 'integer', 'min:1'],
'lines.*.sub_recipe_version_id' => ['nullable', 'integer', 'min:1'],
'lines.*.uom_id' => ['required', 'integer', 'min:1'],
'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'regex:/\A\d{1,14}(?:\.\d{1,4})?\z/'],
'lines.*.waste_percent' => ['required', 'numeric', 'between:0,100', 'regex:/\A\d{1,3}(?:\.\d{1,4})?\z/'],
'recipe_id' => ['missing'], 'version_no' => ['missing'], 'status' => ['missing'],
'approved_by' => ['missing'], 'approved_at' => ['missing'], 'calculated_cost' => ['missing'],
'organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'deleted_at' => ['missing'],
]; }
public function messages(): array { return ['required' => ':attribute wajib diisi.', 'missing' => ':attribute ditentukan oleh sistem.', 'regex' => 'Format atau presisi :attribute tidak valid.', 'gt' => ':attribute harus lebih besar dari nol.', 'between' => ':attribute di luar batas yang diizinkan.', 'lines.list' => 'Urutan baris BOM tidak valid.', 'lines.*.array' => 'Kolom baris BOM tidak valid.']; }
}
