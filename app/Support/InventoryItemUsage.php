<?php

namespace App\Support;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;

class InventoryItemUsage
{
    private const REFERENCES = [
        ['product_variants', 'inventory_item_id'], ['recipes', 'output_item_id'], ['recipe_lines', 'inventory_item_id'],
        ['supplier_items', 'inventory_item_id'], ['purchase_requisition_lines', 'inventory_item_id'],
        ['purchase_order_lines', 'inventory_item_id'], ['goods_receipt_lines', 'inventory_item_id'],
        ['inventory_batches', 'inventory_item_id'], ['inventory_balances', 'inventory_item_id'],
        ['inventory_reservations', 'inventory_item_id'], ['inventory_movements', 'inventory_item_id'],
        ['production_material_requirements', 'inventory_item_id'], ['production_material_usages', 'inventory_item_id'],
        ['stock_transfer_lines', 'inventory_item_id'], ['stock_count_lines', 'inventory_item_id'],
        ['inventory_reconciliation_lines', 'inventory_item_id'], ['waste_records', 'inventory_item_id'],
    ];

    public static function inUse(InventoryItem $item, bool $lock = false): bool
    {
        foreach (self::REFERENCES as [$table, $column]) {
            $query = DB::table($table)->where($column, $item->id);
            if ($lock) {
                $query->lockForUpdate();
            }
            if ($query->first(['id'])) {
                return true;
            }
        }

        return false;
    }
}
