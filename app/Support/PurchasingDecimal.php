<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PurchasingDecimal
{
    public static function money(string $value): string
    {
        if (bccomp($value, '0', 8) < 0) {
            throw ValidationException::withMessages(['discount_amount' => 'Diskon melebihi subtotal dan pajak.']);
        }
        $rounded = bcadd($value, '0.005', 2);
        if (bccomp($rounded, '9999999999999999.99', 2) > 0 || bccomp($rounded, '0', 2) < 0) {
            throw ValidationException::withMessages(['total_amount' => 'Nilai dokumen melebihi batas nominal database.']);
        }

        return $rounded;
    }

    public static function max(string $a, string $b): string
    {
        return bccomp($a, $b, 4) >= 0 ? $a : $b;
    }
}
