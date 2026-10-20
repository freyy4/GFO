<?php

namespace App\Http\Requests;

use App\Models\ProductCategory;

class UpdateProductCategoryRequest extends StoreProductCategoryRequest
{
    public function authorize(): bool
    {
        $record = $this->route('product_category');

        return $record instanceof ProductCategory && ($this->user()?->can('update', $record) ?? false);
    }
}
