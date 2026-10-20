<?php

namespace App\Http\Requests;

use App\Models\OperationalUnit;

class UpdateOperationalUnitRequest extends StoreOperationalUnitRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('operational_unit');

        return $unit instanceof OperationalUnit && ($this->user()?->can('update', $unit) ?? false);
    }
}
