<?php

namespace App\Support;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UomUsage
{
    /** All existing foreign keys, including conversions and soft-deleted historical references. */
    private const REFERENCES = [
        ['uom_conversions', 'from_uom_id'], ['uom_conversions', 'to_uom_id'],
        ['inventory_items', 'base_uom_id'], ['recipe_versions', 'yield_uom_id'], ['recipe_lines', 'uom_id'],
        ['supplier_items', 'purchase_uom_id'], ['purchase_requisition_lines', 'uom_id'],
        ['purchase_order_lines', 'uom_id'], ['goods_receipt_lines', 'uom_id'],
        ['production_material_requirements', 'uom_id'], ['production_material_usages', 'uom_id'],
        ['stock_transfer_lines', 'uom_id'],
    ];

    public static function query(?string $usage = null): Builder
    {
        $expression = implode(' OR ', array_map(
            fn (array $reference) => 'EXISTS (SELECT 1 FROM '.$reference[0].' WHERE '.$reference[0].'.'.$reference[1].' = units_of_measure.id)',
            self::REFERENCES,
        ));
        $query = UnitOfMeasure::query()->select('units_of_measure.*')->selectRaw('('.$expression.') AS is_used');
        if (in_array($usage, ['used', 'unused'], true)) {
            $query->whereRaw(($usage === 'unused' ? 'NOT ' : '').'('.$expression.')');
        }

        return $query;
    }

    public static function lockedInUse(UnitOfMeasure $uom): bool
    {
        foreach (self::REFERENCES as [$table, $column]) {
            if (DB::table($table)->where($column, $uom->id)->lockForUpdate()->first(['id'])) {
                return true;
            }
        }

        return false;
    }
}
