<?php

namespace App\Support;

use App\Http\Requests\PurchasingSaveRequest;
use App\Models\AuditLog;
use App\Models\InventoryBalance;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierContract;
use App\Models\SupplierPriceHistory;
use App\Models\UnitOfMeasure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchasingMutation
{
    public static function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public static function timezone(Request $request): string
    {
        $tz = $request->user()->organization?->timezone;

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    private function lock(Request $request): void
    {
        Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
        PurchasingAccess::forget($request->user());
    }

    public function snapshot(Model $record, string $module): array
    {
        $data = $record->attributesToArray();
        if (PurchasingDefinition::document($module)) {
            $data['lines'] = $record->{PurchasingDefinition::relation($module)}()->orderBy('id')->get()->toArray();
        }

        return $data;
    }

    public function audit(Request $request, Model $record, string $module, string $event, array $before): void
    {
        AuditLog::create(['organization_id' => $request->user()->organization_id, 'user_id' => $request->user()->id,
            'event' => str_replace('-', '_', $module).'.'.$event, 'auditable_type' => $record->getMorphClass(), 'auditable_id' => $record->id,
            'old_values' => $before, 'new_values' => $record->exists ? $this->snapshot($record, $module) : null,
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }

    public function save(PurchasingSaveRequest $request, ?Model $record = null): Model
    {
        $module = $request->module();
        try {
            return DB::transaction(function () use ($request, $record, $module) {
                $this->lock($request);
                $class = PurchasingDefinition::MODELS[$module];
                $record = $record ? PurchasingAccess::query($request->user(), $module)->lockForUpdate()->findOrFail($record->id) : new $class;
                $record->exists ? Gate::authorize('purchasing-action', [$module, $record, 'update']) : Gate::authorize('purchasing-create', $module);
                $data = $request->validated();
                $lines = $data['lines'] ?? [];
                unset($data['lines']);
                $before = $record->exists ? $this->snapshot($record, $module) : [];
                $event = $record->exists ? 'update' : 'create';
                if (PurchasingDefinition::document($module)) {
                    $lines = $this->document($request, $module, $record, $data, $lines);
                    if (! $record->exists) {
                        $prefix = ['purchase-requisitions' => 'PR', 'purchase-orders' => 'PO', 'goods-receipts' => 'GR'][$module];
                        $data['number'] = $prefix.'-'.now()->format('Ymd').'-'.Str::ulid();
                        $data['status'] = 'DRAFT';
                        $data[match ($module) {
                            'purchase-requisitions' => 'requested_by', 'purchase-orders' => 'created_by', default => 'received_by'
                        }] = $request->user()->id;
                    }
                } else {
                    $this->referenceData($request, $module, $record, $data);
                }
                $record->fill($data)->save();
                if (PurchasingDefinition::document($module)) {
                    if ($module === 'goods-receipts' && DB::table('inventory_batches')->whereIn('goods_receipt_line_id', $record->goodsReceiptLines()->select('id'))->exists()) {
                        self::reject('record', 'Penerimaan sudah digunakan oleh inventory dan tidak dapat diubah.');
                    }
                    $relation = PurchasingDefinition::relation($module);
                    $record->$relation()->delete();
                    $record->$relation()->createMany($lines);
                }
                $this->audit($request, $record, $module, $event, $before);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            self::reject('record', 'Data sudah tersedia atau bentrok dengan perubahan lain. Periksa pasangan supplier/item dan nomor dokumen.');
        }
    }

    private function document(Request $request, string $module, Model $record, array &$data, array $lines, bool $confirm = false, ?string $abilityOverride = null): array
    {
        $user = $request->user();
        $org = $user->organization_id;
        $ability = $record->exists ? 'update' : 'create';
        if ($confirm) {
            $ability = $module === 'goods-receipts' ? 'confirm' : 'approve';
        }
        $ability = $abilityOverride ?? $ability;
        if ($module === 'goods-receipts') {
            $po = PurchasingAccess::orders($user, $module)->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->lockForUpdate()->find($data['purchase_order_id']);
            if (! $po) {
                self::reject('purchase_order_id', 'PO harus disetujui, belum selesai, dan berada dalam akses Anda.');
            }
            $data['supplier_id'] = $po->supplier_id;
            $data['destination_unit_id'] = $po->destination_unit_id;
            if ($record->exists && (int) $record->purchase_order_id !== (int) $po->id) {
                self::reject('purchase_order_id', 'PO penerimaan tidak dapat diganti.');
            }
        }
        $unit = PurchasingAccess::units($user, $module)->where('status', 'ACTIVE')->lockForUpdate()->find($data['destination_unit_id']);
        if (! $unit || ! PurchasingAccess::canWrite($user, $module, $ability, $unit->id)) {
            self::reject('destination_unit_id', 'Unit tujuan aktif dan izin tindakan diperlukan.');
        }
        if ($module !== 'goods-receipts') {
            $data['organization_id'] = $org;
        }
        if ($module === 'purchase-orders' || $module === 'goods-receipts') {
            if (! PurchasingAccess::suppliers($user)->where('approval_status', 'APPROVED')->lockForUpdate()->find($data['supplier_id'])) {
                self::reject('supplier_id', 'Supplier harus tersedia dan berstatus APPROVED.');
            }
        }
        if ($module === 'purchase-orders' && ! empty($data['purchase_requisition_id'])) {
            $pr = PurchasingAccess::requisitions($user, $module)->where('status', 'APPROVED')->where('destination_unit_id', $unit->id)->lockForUpdate()->find($data['purchase_requisition_id']);
            if (! $pr) {
                self::reject('purchase_requisition_id', 'Requisition harus APPROVED dan memiliki unit tujuan yang sama.');
            }
            $prLines = $pr->purchaseRequisitionLines()->lockForUpdate()->get()->keyBy('inventory_item_id');
        }
        $prepared = [];
        $subtotal = '0.00';
        foreach ($lines as $i => $line) {
            $field = 'lines.'.$i;
            if ($module === 'goods-receipts') {
                $poLine = $po->purchaseOrderLines()->lockForUpdate()->find($line['purchase_order_line_id']);
                if (! $poLine) {
                    self::reject($field.'.purchase_order_line_id', 'Baris bukan bagian dari PO yang dipilih.');
                }
                $item = PurchasingAccess::items($user, $module)->lockForUpdate()->find($poLine->inventory_item_id);
                if (! $item || $poLine->uom_id !== $item->base_uom_id) {
                    self::reject($field, 'Item atau satuan dasar PO tidak tersedia/berubah.');
                }
                $uom = UnitOfMeasure::findOrFail($poLine->uom_id);
                foreach (['received_quantity', 'accepted_quantity', 'rejected_quantity'] as $quantityKey) {
                    if (bccomp((string) $line[$quantityKey], bcadd((string) $line[$quantityKey], '0', min(4, $uom->decimal_places)), 4) !== 0) {
                        self::reject($field.'.'.$quantityKey, 'Ketelitian kuantitas melebihi satuan item.');
                    }
                }
                $remaining = bcsub($poLine->ordered_quantity, $poLine->received_quantity, 4);
                if (bccomp((string) $line['received_quantity'], $remaining, 4) > 0) {
                    self::reject($field.'.received_quantity', 'Penerimaan melebihi sisa PO yang belum diterima/lolos QC.');
                }
                $classified = bcadd((string) $line['accepted_quantity'], (string) $line['rejected_quantity'], 4);
                if (bccomp($classified, (string) $line['received_quantity'], 4) > 0 || ($confirm && bccomp($classified, (string) $line['received_quantity'], 4) !== 0)) {
                    self::reject($field.'.accepted_quantity', 'Saat konfirmasi, jumlah lolos dan ditolak QC harus sama dengan jumlah diterima.');
                }
                if (bccomp((string) $line['rejected_quantity'], '0', 4) > 0 && empty($line['rejection_reason'])) {
                    self::reject($field.'.rejection_reason', 'Alasan penolakan wajib diisi.');
                }
                if (! empty($line['manufactured_on']) && ! empty($line['expires_on']) && $line['expires_on'] < $line['manufactured_on']) {
                    self::reject($field.'.expires_on', 'Kedaluwarsa tidak boleh sebelum tanggal produksi.');
                }
                if ($confirm && bccomp((string) $line['accepted_quantity'], '0', 4) > 0) {
                    if ($item->tracking_policy !== 'NONE' && empty($line['supplier_batch_no'])) {
                        self::reject($field.'.supplier_batch_no', 'Batch wajib untuk item dengan pelacakan lot.');
                    }
                    if ($item->tracking_policy === 'LOT_EXPIRY' && empty($line['expires_on'])) {
                        self::reject($field.'.expires_on', 'Tanggal kedaluwarsa wajib.');
                    }
                    if (! empty($line['expires_on']) && $line['expires_on'] < CarbonImmutable::parse($data['received_at'])->format('Y-m-d')) {
                        self::reject($field.'.expires_on', 'Barang kedaluwarsa tidak boleh lolos QC.');
                    }
                }
                $prepared[] = [...$line, 'inventory_item_id' => $item->id, 'uom_id' => $poLine->uom_id, 'unit_cost' => $poLine->unit_price,
                    'qc_status' => ! $confirm ? 'PENDING' : (bccomp((string) $line['rejected_quantity'], '0', 4) > 0 ? (bccomp((string) $line['accepted_quantity'], '0', 4) > 0 ? 'PARTIAL' : 'REJECTED') : 'ACCEPTED')];
            } else {
                $item = PurchasingAccess::items($user, $module)->lockForUpdate()->find($line['inventory_item_id']);
                if (! $item || ($item->brand_id && (int) $item->brand_id !== (int) $unit->brand_id)) {
                    self::reject($field.'.inventory_item_id', 'Item tidak aktif, di luar organization, atau tidak sesuai brand unit tujuan.');
                }
                $uom = UnitOfMeasure::lockForUpdate()->findOrFail($item->base_uom_id);
                $quantity = (string) $line[$module === 'purchase-requisitions' ? 'required_quantity' : 'ordered_quantity'];
                if (bccomp($quantity, bcadd($quantity, '0', min(4, $uom->decimal_places)), 4) !== 0) {
                    self::reject($field, 'Ketelitian kuantitas melebihi satuan dasar item.');
                }
                if ($module === 'purchase-requisitions') {
                    $available = $this->available($unit->id, $item->id);
                    $shortage = PurchasingDecimal::max('0', bcsub($quantity, $available, 4));
                    $prepared[] = ['inventory_item_id' => $item->id, 'uom_id' => $item->base_uom_id, 'required_quantity' => $quantity,
                        'available_quantity_snapshot' => $available, 'shortage_quantity' => $shortage, 'recommended_quantity' => $shortage];
                } else {
                    if (isset($prLines)) {
                        $prLine = $prLines->get($item->id);
                        if (! $prLine || $prLine->uom_id !== $item->base_uom_id) {
                            self::reject($field, 'Item/satuan tidak terdapat pada requisition terkait.');
                        }
                        $allocated = '0';
                        $otherLines = PurchaseOrderLine::where('inventory_item_id', $item->id)->whereHas('purchaseOrder', fn ($q) => $q
                            ->where('purchase_requisition_id', $pr->id)->where('id', '!=', $record->id ?? 0)->whereNotIn('status', ['CANCELLED', 'REJECTED']))->lockForUpdate()->get();
                        foreach ($otherLines as $other) {
                            $allocated = bcadd($allocated, $other->ordered_quantity, 4);
                        }
                        if (bccomp(bcadd($allocated, $quantity, 4), $prLine->required_quantity, 4) > 0) {
                            self::reject($field.'.ordered_quantity', 'Total alokasi PO melebihi kuantitas requisition.');
                        }
                    }
                    $total = PurchasingDecimal::money(bcmul($quantity, (string) $line['unit_price'], 8));
                    $subtotal = bcadd($subtotal, $total, 2);
                    $prepared[] = ['inventory_item_id' => $item->id, 'uom_id' => $item->base_uom_id, 'ordered_quantity' => $quantity,
                        'received_quantity' => '0', 'unit_price' => $line['unit_price'], 'line_total' => $total];
                }
            }
        }
        if (! $prepared) {
            self::reject('lines', 'Dokumen memerlukan minimal satu baris.');
        }
        if ($module === 'purchase-orders') {
            $data['subtotal'] = PurchasingDecimal::money($subtotal);
            $total = bcsub(bcadd($subtotal, (string) $data['tax_amount'], 2), (string) $data['discount_amount'], 2);
            $data['total_amount'] = PurchasingDecimal::money($total);
        }
        if (! $confirm) {
            foreach (['required_at', 'expected_at', 'received_at'] as $key) {
                if (! empty($data[$key])) {
                    $data[$key] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data[$key], self::timezone($request))->utc();
                }
            }
        }

        return $prepared;
    }

    public function available(int $unitId, int $itemId): string
    {
        $total = '0';
        foreach (InventoryBalance::where('operational_unit_id', $unitId)->where('inventory_item_id', $itemId)->lockForUpdate()->get() as $balance) {
            $total = bcadd($total, bcsub(bcsub($balance->on_hand, $balance->reserved, 4), $balance->quarantined, 4), 4);
        }

        return PurchasingDecimal::max('0', $total);
    }

    private function referenceData(Request $request, string $module, Model $record, array &$data): void
    {
        $user = $request->user();
        if (isset($data['supplier_id']) && ! PurchasingAccess::suppliers($user)->lockForUpdate()->find($data['supplier_id'])) {
            self::reject('supplier_id', 'Supplier tidak tersedia.');
        }
        if ($record->exists && isset($data['supplier_id']) && (int) $record->supplier_id !== (int) $data['supplier_id']) {
            self::reject('supplier_id', 'Supplier tidak dapat diganti.');
        }
        if ($module === 'supplier-contracts') {
            if (SupplierContract::where('supplier_id', $data['supplier_id'])->where('contract_no', $data['contract_no'])->where('id', '!=', $record->id ?? 0)->exists()) {
                self::reject('contract_no', 'Nomor kontrak telah digunakan untuk supplier ini.');
            }
            if (! $record->exists) {
                $data['status'] = 'DRAFT';
            }
        } elseif ($module === 'supplier-items') {
            $item = PurchasingAccess::items($user, $module)->lockForUpdate()->find($data['inventory_item_id']);
            if (! $item) {
                self::reject('inventory_item_id', 'Item aktif diperlukan dalam organization Anda.');
            }
            if ($record->exists && (int) $record->inventory_item_id !== (int) $item->id) {
                self::reject('inventory_item_id', 'Identitas item tidak dapat diganti.');
            }
            if ($record->exists && $record->purchase_uom_id !== $item->base_uom_id && $record->supplierPriceHistories()->exists()) {
                self::reject('inventory_item_id', 'Satuan item telah berubah; riwayat harga lama harus dipertahankan.');
            }
            $data['purchase_uom_id'] = $item->base_uom_id;
            if (! $record->exists) {
                $data['is_active'] = true;
            }
        } else {
            $item = PurchasingAccess::supplierItems($user, $module)->where('is_active', true)->whereHas('supplier')->whereHas('inventoryItem', fn ($q) => $q->where('is_active', true))->lockForUpdate()->find($data['supplier_item_id']);
            if (! $item) {
                self::reject('supplier_item_id', 'Barang supplier harus aktif dan tersedia.');
            }
            if ($record->exists && (int) $record->supplier_item_id !== (int) $item->id) {
                self::reject('supplier_item_id', 'Identitas barang supplier tidak dapat diganti.');
            }
            if ($record->exists && $data['effective_from'] <= now(self::timezone($request))->format('Y-m-d')) {
                self::reject('effective_from', 'Edit riwayat hanya diperbolehkan untuk periode mendatang.');
            }
            $overlap = SupplierPriceHistory::where('supplier_item_id', $item->id)->where('currency', $data['currency'])->where('id', '!=', $record->id ?? 0)
                ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']));
            if (! empty($data['effective_until'])) {
                $overlap->whereDate('effective_from', '<=', $data['effective_until']);
            }
            if ($overlap->lockForUpdate()->first()) {
                self::reject('effective_from', 'Periode harga tumpang tindih. Tutup periode sebelumnya sebelum membuat harga baru.');
            }
            $data['recorded_by'] = $user->id;
        }
    }

    public function action(Request $request, string $module, Model $record, string $action, array $data = []): void
    {
        DB::transaction(function () use ($request, $module, $record, $action, $data) {
            $this->lock($request);
            $record = PurchasingAccess::query($request->user(), $module)->lockForUpdate()->findOrFail($record->id);
            Gate::authorize('purchasing-action', [$module, $record, $action]);
            $before = $this->snapshot($record, $module);
            if ($action === 'delete') {
                $this->guardDeletion($record, $module);
                $record->delete();
            } elseif ($action === 'confirm') {
                $header = $record->attributesToArray();
                $lines = $record->goodsReceiptLines()->orderBy('id')->lockForUpdate()->get();
                $input = $lines->map(fn ($line) => array_intersect_key($line->attributesToArray(), PurchasingDefinition::lineFields($module)))->all();
                $prepared = $this->document($request, $module, $record, $header, $input, true);
                $po = PurchaseOrder::lockForUpdate()->findOrFail($record->purchase_order_id);
                $poBefore = $this->snapshot($po, 'purchase-orders');
                foreach ($lines as $i => $line) {
                    $line->update(['qc_status' => $prepared[$i]['qc_status']]);
                    $poLine = $po->purchaseOrderLines()->lockForUpdate()->findOrFail($line->purchase_order_line_id);
                    $poLine->update(['received_quantity' => bcadd($poLine->received_quantity, $line->accepted_quantity, 4)]);
                }
                $remaining = $po->purchaseOrderLines()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists();
                $any = $po->purchaseOrderLines()->where('received_quantity', '>', 0)->exists();
                $po->update(['status' => ! $remaining ? 'RECEIVED' : ($any ? 'PARTIALLY_RECEIVED' : 'APPROVED')]);
                $record->update(['status' => 'CONFIRMED', 'qc_by' => $request->user()->id]);
                $this->audit($request, $po, 'purchase-orders', 'receipt_progress', $poBefore);
            } elseif ($action === 'close') {
                if ($data['effective_until'] < now(self::timezone($request))->format('Y-m-d') || $data['effective_until'] < $record->effective_from->format('Y-m-d')) {
                    self::reject('effective_until', 'Penutupan tidak boleh sebelum hari ini atau awal periode.');
                }
                $record->update(['effective_until' => $data['effective_until']]);
            } elseif (in_array($action, ['activate', 'deactivate'], true)) {
                if ($module === 'supplier-items') {
                    $record->update(['is_active' => $action === 'activate']);
                } else {
                    if ($action === 'activate' && $record->ends_on && $record->ends_on->format('Y-m-d') < now(self::timezone($request))->format('Y-m-d')) {
                        self::reject('ends_on', 'Kontrak kedaluwarsa tidak dapat diaktifkan.');
                    }
                    $record->update(['status' => $action === 'activate' ? 'ACTIVE' : 'INACTIVE']);
                }
            } else {
                if ($action === 'cancel') {
                    $this->guardDeletion($record, $module);
                }
                if (in_array($action, ['submit', 'approve'], true)) {
                    $header = $record->attributesToArray();
                    $lines = $record->{PurchasingDefinition::relation($module)}()->lockForUpdate()->get()->map(fn ($line) => array_intersect_key($line->attributesToArray(), PurchasingDefinition::lineFields($module)))->all();
                    // Approval revalidates supplier, quantities, references, and allocations under locks.
                    $this->document($request, $module, $record, $header, $lines, true, $action === 'submit' ? 'update' : 'approve');
                }
                $values = ['status' => match ($action) {
                    'submit' => 'SUBMITTED', 'approve' => 'APPROVED', 'reject' => 'REJECTED', 'cancel' => 'CANCELLED'
                }];
                if ($action === 'approve') {
                    $values += ['approved_by' => $request->user()->id, 'approved_at' => now()];
                }
                if ($action === 'submit' || $action === 'reject') {
                    $values += ['approved_by' => null, 'approved_at' => null];
                }
                $record->update($values);
            }
            $this->audit($request, $record, $module, $action, $before);
        }, 3);
    }

    private function guardDeletion(Model $record, string $module): void
    {
        $used = match ($module) {
            'purchase-requisitions' => $record->purchaseOrders()->withTrashed()->exists(),
            'purchase-orders' => $record->goodsReceipts()->where('status', '!=', 'CANCELLED')->exists() || $record->purchaseOrderLines()->where('received_quantity', '>', 0)->exists(),
            'goods-receipts' => DB::table('inventory_batches')->whereIn('goods_receipt_line_id', $record->goodsReceiptLines()->select('id'))->exists(),
            'supplier-items' => $record->supplierPriceHistories()->exists(), default => false,
        };
        if ($used) {
            self::reject('record', 'Dokumen sudah digunakan oleh dokumen lanjutan dan tidak dapat dihapus/dibatalkan.');
        }
    }
}
