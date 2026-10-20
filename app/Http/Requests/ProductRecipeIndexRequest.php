<?php
namespace App\Http\Requests;
use App\Models\{Product, Recipe};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ProductRecipeIndexRequest extends FormRequest
{
 public function authorize(): bool { return $this->user()->can('viewAny', $this->routeIs('products.*') ? Product::class : Recipe::class); }
 public function rules(): array { return ['search' => ['nullable', 'string', 'max:255'], 'brand_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', Rule::in($this->routeIs('recipes.versions.*') ? ['DRAFT', 'APPROVED'] : ['ACTIVE', 'INACTIVE', 'ARCHIVED'])], 'page' => ['nullable', 'integer', 'min:1']]; }
}
