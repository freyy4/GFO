<?php

namespace App\Http\Requests;

use App\Models\UnitOfMeasure;

class UpdateUomRequest extends StoreUomRequest
{
    public function authorize(): bool
    {
        $uom = $this->route('uom');

        return $uom instanceof UnitOfMeasure && ($this->user()?->can('update', $uom) ?? false);
    }
}
