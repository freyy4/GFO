<?php

namespace App\Http\Requests;

use App\Models\ProductCategory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductCategory::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        } elseif (is_string($this->input('slug'))) {
            $this->merge(['slug' => strtolower(trim($this->input('slug')))]);
        }
    }

    public function rules(): array
    {
        $category = $this->route('product_category');
        $currentBrand = $category instanceof ProductCategory ? $category->brand_id : null;

        return [
            'brand_id' => ['required', 'integer', 'min:1', Rule::exists('brands', 'id')->where('organization_id', $this->user()->organization_id)
                ->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $currentBrand ?? 0))],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['bail', 'required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                if (! is_scalar($this->input('brand_id'))) {
                    return;
                }
                if (ProductCategory::whereHas('brand', fn ($query) => $query->withTrashed()->where('organization_id', $this->user()->organization_id))->where('brand_id', $this->input('brand_id'))->whereRaw('LOWER(slug) = ?', [strtolower($value)])
                    ->when($category instanceof ProductCategory, fn ($query) => $query->where('id', '!=', $category->id))->exists()) {
                    $fail('Slug sudah digunakan dalam brand ini.');
                }
            }],
            'sort_order' => ['required', 'integer', 'between:0,4294967295'],
            'is_active' => ['required', 'boolean'],
            'organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'deleted_at' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return ['required' => ':attribute wajib diisi.', 'brand_id.exists' => 'Brand tidak tersedia dalam organization Anda.',
            'slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'missing' => ':attribute ditentukan oleh sistem.', 'is_active.boolean' => 'Pilih status aktif atau nonaktif.'];
    }

    public function attributes(): array
    {
        return ['brand_id' => 'Brand', 'parent_id' => 'Kategori induk', 'name' => 'Nama kategori', 'sort_order' => 'Urutan', 'is_active' => 'Status'];
    }
}
