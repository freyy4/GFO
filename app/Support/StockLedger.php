<?php

namespace App\Support;

use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\OperationalUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StockLedger
{
    public static function fail(string $message, string $field = 'record'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public static function money(string $v): string
    {
        if (bccomp($v, '0', 8) < 0) {
            self::fail('Nominal tidak boleh negatif.');
        }
        $v = bcadd($v, '0.005', 2);
        if (bccomp($v, '9999999999999999.99', 2) > 0) {
            self::fail('Nominal melebihi kapasitas database.');
        }

        return $v;
    }

    public static function signedMoney(string $v): string
    {
        return bccomp($v, '0', 8) < 0 ? '-'.self::money(bcmul($v, '-1', 8)) : self::money($v);
    }

    public static function quantity(string $v): string
    {
        if (bccomp($v, '99999999999999.9999', 4) > 0 || bccomp($v, '-99999999999999.9999', 4) < 0) {
            self::fail('Kuantitas melebihi kapasitas database.');
        }

        return bcadd($v, '0', 4);
    }

    public static function precision(InventoryItem $item, string $qty): void
    {
        if (bccomp($qty, bcadd($qty, '0', min(4, $item->baseUom->decimal_places)), 4) !== 0) {
            self::fail('Ketelitian kuantitas melebihi satuan dasar item.');
        }
    }

    public function item(int $unitId, int $itemId): InventoryItem
    {
        $unit = OperationalUnit::where('organization_id', auth()->user()->organization_id)->where('status', 'ACTIVE')->findOrFail($unitId);
        $item = InventoryItem::where('organization_id', $unit->organization_id)->where('is_active', true)->lockForUpdate()->find($itemId);
        if (! $item || ($item->brand_id && (int) $item->brand_id !== (int) $unit->brand_id)) {
            self::fail('Item tidak aktif atau tidak sesuai organization/brand unit.', 'inventory_item_id');
        }

        return $item;
    }

    public function batch(InventoryItem $item, ?int $batchId, bool $usable = true): ?InventoryBatch
    {
        $batch = $batchId ? InventoryBatch::where('inventory_item_id', $item->id)->lockForUpdate()->find($batchId) : null;
        if (($batchId && ! $batch) || (! $batchId && $item->tracking_policy !== 'NONE')) {
            self::fail('Batch harus sesuai dengan item; item lot wajib memiliki batch.', 'inventory_batch_id');
        }
        if ($usable && $batch && ($batch->qc_status !== 'RELEASED' || ($batch->expires_on && $batch->expires_on->format('Y-m-d') < now(TradeMutation::timezone())->format('Y-m-d')))) {
            self::fail('Batch dikarantina atau sudah kedaluwarsa.', 'inventory_batch_id');
        }

        return $batch;
    }

    public function balance(int $unit, int $item, ?int $batch): InventoryBalance
    {
        $rows = InventoryBalance::where('operational_unit_id', $unit)->where('inventory_item_id', $item)->where('inventory_batch_id', $batch)->lockForUpdate()->get();
        if ($rows->count() > 1) {
            self::fail('Saldo stok duplikat ditemukan. Perbaiki integritas data sebelum posting.');
        }

        return $rows->first() ?? InventoryBalance::create(['operational_unit_id' => $unit, 'inventory_item_id' => $item, 'inventory_batch_id' => $batch])->refresh();
    }

    public function available(InventoryBalance $b): string
    {
        return bcsub(bcsub((string) $b->on_hand, (string) $b->reserved, 4), (string) $b->quarantined, 4);
    }

    public function post(int $unit, int $itemId, ?int $batchId, string $qty, string $type, ?Model $reference, string $reason = '', bool $usable = true, ?string $costOverride = null): InventoryMovement
    {
        $item = $this->item($unit, $itemId);
        self::precision($item, $qty);
        $batch = $this->batch($item, $batchId, $usable);
        $balance = $this->balance($unit, $itemId, $batchId);
        $available = $type === 'WASTE' ? bcsub($balance->on_hand, $balance->reserved, 4) : $this->available($balance);
        if (bccomp($qty, '0', 4) < 0 && bccomp($available, bcmul($qty, '-1', 4), 4) < 0) {
            self::fail('Stok tersedia tidak mencukupi; stok reservasi/karantina tidak dapat dipakai.');
        }
        $after = self::quantity(bcadd((string) $balance->on_hand, $qty, 4));
        if (bccomp($after, '0', 4) < 0) {
            self::fail('Saldo stok tidak boleh negatif.');
        }
        $cost = $costOverride ?? $batch?->unit_cost ?? $item->standard_cost;
        $total = self::quantity(bcmul($qty, $cost, 4));
        if ($type === 'WASTE' && bccomp($balance->quarantined, '0', 4) > 0) {
            $removed = bcmul($qty, '-1', 4);
            $balance->quarantined = bccomp($removed, $balance->quarantined, 4) >= 0 ? '0' : bcsub($balance->quarantined, $removed, 4);
        }
        $balance->fill(['on_hand' => $after])->save();

        return InventoryMovement::create(['organization_id' => auth()->user()->organization_id, 'operational_unit_id' => $unit, 'inventory_item_id' => $itemId,
            'inventory_batch_id' => $batchId, 'movement_type' => $type, 'quantity' => $qty, 'unit_cost' => $cost, 'total_cost' => $total, 'balance_after' => $after,
            'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->id, 'performed_by' => auth()->id(), 'occurred_at' => now(), 'reason' => $reason]);
    }

    public function consume(int $unit, int $itemId, string $qty, Model $reference): string
    {
        $item = $this->item($unit, $itemId);
        self::precision($item, $qty);
        $rows = InventoryBalance::where('operational_unit_id', $unit)->where('inventory_item_id', $itemId)->lockForUpdate()->get();
        $batches = InventoryBatch::where('inventory_item_id', $itemId)->get()->keyBy('id');
        $rows = $rows->sortBy(function ($b) use ($batches, $item) {
            $batch = $batches->get($b->inventory_batch_id);

            return $item->issue_policy === 'FEFO' ? ($batch?->expires_on?->format('Y-m-d') ?? '9999-12-31').sprintf('%020d', $b->id) : ($batch?->received_on?->format('Y-m-d') ?? $b->created_at->format('Y-m-d')).sprintf('%020d', $b->id);
        });
        $remaining = $qty;
        $cost = '0';
        foreach ($rows as $b) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }
            $batch = $batches->get($b->inventory_batch_id);
            if (($b->inventory_batch_id && ! $batch) || (! $batch && $item->tracking_policy !== 'NONE') || ($batch && ($batch->qc_status !== 'RELEASED' || ($batch->expires_on && $batch->expires_on->format('Y-m-d') < now(TradeMutation::timezone())->format('Y-m-d'))))) {
                continue;
            }
            $available = $this->available($b);
            if (bccomp($available, '0', 4) <= 0) {
                continue;
            }
            $take = bccomp($available, $remaining, 4) > 0 ? $remaining : $available;
            $movement = $this->post($unit, $itemId, $b->inventory_batch_id, bcmul($take, '-1', 4), 'SALE', $reference);
            $cost = bcadd($cost, bcmul($movement->total_cost, '-1', 4), 4);
            $remaining = bcsub($remaining, $take, 4);
        }
        if (bccomp($remaining, '0', 4) > 0) {
            self::fail('Stok layak jual tidak mencukupi untuk '.$item->name.'.');
        }

        return $cost;
    }
}
