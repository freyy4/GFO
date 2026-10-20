<?php

namespace App\Support;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\OperationalUnit;
use App\Models\ProductionBatch;
use App\Models\RecipeVersion;

class ProductionBom
{
    public function version(int $id, int $unit, string $date): RecipeVersion
    {
        $v = RecipeVersion::where('status', 'APPROVED')->whereDate('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))->whereHas('recipe', fn ($q) => $q->where('status', 'ACTIVE')->whereHas('brand', fn ($q) => $q->where('organization_id', auth()->user()->organization_id)->where('status', 'ACTIVE')))->lockForUpdate()->find($id);
        if (! $v || bccomp($v->yield_quantity, '0', 4) <= 0) {
            StockLedger::fail('Versi recipe harus aktif, disetujui, dan berlaku pada tanggal produksi.', 'recipe_version_id');
        }
        $unitModel = OperationalUnit::where('organization_id', auth()->user()->organization_id)->findOrFail($unit);
        if ((int) $v->recipe->brand_id !== (int) $unitModel->brand_id) {
            StockLedger::fail('Brand recipe harus sama dengan unit produksi.');
        }

        return $v;
    }

    public function output(RecipeVersion $v, int $unit): InventoryItem
    {
        $id = $v->recipe->output_item_id ?? $v->recipe->productVariant?->inventory_item_id;
        if (! $id) {
            StockLedger::fail('Recipe memerlukan item hasil.');
        }$item = (new StockLedger)->item($unit, $id);
        if ($item->base_uom_id !== $v->yield_uom_id) {
            StockLedger::fail('Satuan hasil recipe harus sama dengan satuan dasar item hasil.');
        }

        return $item;
    }

    public static function roundUp(string $value, int $places): string
    {
        $truncated = bcadd($value, '0', $places);
        if (bccomp($value, $truncated, 12) > 0) {
            $truncated = bcadd($truncated, $places ? '0.'.str_repeat('0', $places - 1).'1' : '1', $places);
        }

        return StockLedger::quantity($truncated);
    }

    public function requirements(RecipeVersion $v, int $unit, string $date, string $qty): array
    {
        StockLedger::precision($this->output($v, $unit), $qty);
        $raw = $this->expand($v, $unit, $date, $qty, []);
        if (isset($raw[$this->output($v, $unit)->id])) {
            StockLedger::fail('Item hasil tidak boleh menjadi bahan recipe sendiri.');
        }
        $result = [];
        foreach ($raw as $id => $amount) {
            $item = (new StockLedger)->item($unit, $id);
            $result[$id] = self::roundUp($amount, min(4, $item->baseUom->decimal_places));
        }

        return $result;
    }

    private function expand(RecipeVersion $v, int $unit, string $date, string $qty, array $path): array
    {
        if (in_array($v->id, $path, true) || count($path) >= 20) {
            StockLedger::fail('Recipe memiliki siklus atau terlalu banyak tingkat sub-recipe.');
        }$path[] = $v->id;
        $lines = $v->recipeLinesAsRecipeVersion()->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            StockLedger::fail('Recipe belum memiliki bahan.');
        }$out = [];
        $factor = bcdiv($qty, $v->yield_quantity, 12);
        foreach ($lines as $line) {
            if (bccomp($line->quantity, '0', 4) <= 0 || bccomp($line->waste_percent, '0', 4) < 0) {
                StockLedger::fail('Kuantitas atau waste recipe tidak valid.');
            }
            $amount = bcmul(bcmul($line->quantity, $factor, 12), bcadd('1', bcdiv($line->waste_percent, '100', 12), 12), 12);
            if ($line->inventory_item_id && ! $line->sub_recipe_version_id) {
                $item = (new StockLedger)->item($unit, $line->inventory_item_id);
                if ($item->base_uom_id !== $line->uom_id || $item->id === ($v->recipe->output_item_id ?? $v->recipe->productVariant?->inventory_item_id)) {
                    StockLedger::fail('Bahan recipe atau satuannya tidak valid.');
                }$parts = [$item->id => $amount];
            } elseif ($line->sub_recipe_version_id && ! $line->inventory_item_id) {
                $sub = $this->version($line->sub_recipe_version_id, $unit, $date);
                if ($sub->yield_uom_id !== $line->uom_id) {
                    StockLedger::fail('Satuan sub-recipe tidak sesuai.');
                }$parts = $this->expand($sub, $unit, $date, $amount, $path);
            } else {
                StockLedger::fail('Baris recipe harus memiliki bahan atau sub-recipe tunggal.');
            }
            foreach ($parts as $id => $part) {
                $out[$id] = bcadd($out[$id] ?? '0', $part, 12);
            }
        }

        return $out;
    }

    public function available(int $unit, int $item): string
    {
        $ledger = new StockLedger;
        $sum = '0';
        foreach ($this->usable($unit, $item) as $b) {
            $a = $ledger->available($b);
            if (bccomp($a, '0', 4) > 0) {
                $sum = bcadd($sum, $a, 4);
            }
        }

        return $sum;
    }

    private function usable(int $unit, int $id)
    {
        $item = (new StockLedger)->item($unit, $id);
        $rows = InventoryBalance::where('operational_unit_id', $unit)->where('inventory_item_id', $id)->with('inventoryBatch')->lockForUpdate()->get();

        return $rows->filter(function ($b) use ($item) {
            $batch = $b->inventoryBatch;

            return (! $b->inventory_batch_id && $item->tracking_policy === 'NONE') || ($batch && $batch->inventory_item_id === $item->id && $batch->qc_status === 'RELEASED' && (! $batch->expires_on || $batch->expires_on->format('Y-m-d') >= now(TradeMutation::timezone())->format('Y-m-d')));
        })
            ->sortBy(fn ($b) => ($item->issue_policy === 'FEFO' ? ($b->inventoryBatch?->expires_on?->format('Y-m-d') ?? '9999-12-31') : ($b->inventoryBatch?->received_on?->format('Y-m-d') ?? $b->created_at->format('Y-m-d'))).sprintf('%020d', $b->id));
    }

    public function issue(ProductionBatch $batch, array $input, string $reason): void
    {
        $planned = $batch->productionMaterialUsages()->lockForUpdate()->get()->keyBy('id');
        if (count($input) !== $planned->count()) {
            StockLedger::fail('Semua bahan batch harus dicatat.', 'usages');
        }$allocations = [];
        $ledger = new StockLedger;
        foreach ($input as $row) {
            $usage = $planned->get($row['usage_id']);
            if (! $usage) {
                StockLedger::fail('Baris pemakaian bukan milik batch ini.', 'usages');
            }
            $item = $ledger->item($batch->production_unit_id, $usage->inventory_item_id);
            $qty = (string) $row['actual_quantity'];
            StockLedger::precision($item, $qty);
            $remaining = $qty;
            $plannedRemaining = $usage->planned_quantity;
            foreach ($this->usable($batch->production_unit_id, $item->id) as $balance) {
                if (bccomp($remaining, '0', 4) <= 0) {
                    break;
                }$available = $ledger->available($balance);
                if (bccomp($available, '0', 4) <= 0) {
                    continue;
                }
                $take = bccomp($available, $remaining, 4) > 0 ? $remaining : $available;
                $movement = $ledger->post($batch->production_unit_id, $item->id, $balance->inventory_batch_id, bcmul($take, '-1', 4), 'PRODUCTION_ISSUE', $batch, $reason);
                $remaining = bcsub($remaining, $take, 4);
                $plannedPart = bccomp($remaining, '0', 4) === 0 ? $plannedRemaining : bcdiv(bcmul($usage->planned_quantity, $take, 8), $qty, 4);
                $plannedRemaining = bcsub($plannedRemaining, $plannedPart, 4);
                $allocations[] = ['inventory_item_id' => $item->id, 'inventory_batch_id' => $balance->inventory_batch_id, 'uom_id' => $item->base_uom_id, 'planned_quantity' => $plannedPart, 'actual_quantity' => $take, 'unit_cost_snapshot' => $movement->unit_cost];
            }
            if (bccomp($remaining, '0', 4) > 0) {
                StockLedger::fail('Bahan layak pakai tidak cukup: '.$item->name.'.');
            }
        }
        $batch->productionMaterialUsages()->delete();
        $batch->productionMaterialUsages()->createMany($allocations);
    }
}
