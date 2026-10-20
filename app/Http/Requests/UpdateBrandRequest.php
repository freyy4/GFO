<?php

namespace App\Http\Requests;

use App\Models\Brand;

class UpdateBrandRequest extends StoreBrandRequest
{
    public function authorize(): bool
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand && ($this->user()?->can('update', $brand) ?? false);
    }
}
