<?php

namespace App\Support;

use App\Http\Requests\TradeRequest;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\DailyClosing;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\PaymentSettlement;
use App\Models\RegisterSession;
use App\Models\SalesChannel;
use App\Models\SalesOrder;
use App\Models\StockCountLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TradeMutation
{
    public static function timezone(): string
    {
        $tz = auth()->user()->organization?->timezone;

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    private function lock(): void
    {
        Organization::whereKey(auth()->user()->organization_id)->lockForUpdate()->firstOrFail();
        PurchasingAccess::forget(auth()->user());
    }

    public function audit(Model $r, string $m, string $event, array $before = [], array $metadata = []): void
    {
        $relation = TradeDefinition::relation($m);
        $after = $r->exists ? $r->attributesToArray() : [];
        if ($relation && $r->exists) {
            $after['lines'] = $r->$relation()->get()->toArray();
        }
        AuditLog::create(['organization_id' => auth()->user()->organization_id, 'user_id' => auth()->id(), 'event' => str_replace('-', '_', $m).'.'.$event,
            'auditable_type' => $r->getMorphClass(), 'auditable_id' => $r->id, 'old_values' => $before, 'new_values' => $after + $metadata, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }

    private function unit(string $m, int $id, string $ability): int
    {
        $unit = TradeAccess::units(auth()->user(), $m, $ability)->whereIn('id', TradeAccess::units(auth()->user(), $m)->select('id'))->where('status', 'ACTIVE')->find($id);
        if (! $unit) {
            StockLedger::fail('Unit tidak tersedia atau di luar izin tindakan.', 'unit_id');
        }

        return $unit->id;
    }

    public function save(TradeRequest $request, ?Model $record = null): Model
    {
        $m = $request->module();
        $input = $request->validated();
        try {
            return DB::transaction(function () use ($m, $input, $record) {
                $this->lock();
                $class = TradeDefinition::MODELS[$m];
                $r = $record ? TradeAccess::query(auth()->user(), $m)->lockForUpdate()->findOrFail($record->id) : new $class;
                $r->exists ? Gate::authorize('trade-action', [$m, $r, 'update']) : Gate::authorize('trade-create', $m);
                $before = $r->attributesToArray();
                if ($r->exists && ($relation = TradeDefinition::relation($m))) {
                    $before['lines'] = $r->$relation()->get()->toArray();
                }
                $event = $r->exists ? 'update' : 'create';
                $ability = $r->exists ? 'update' : 'create';
                $d = $input;
                unset($d['submission_token']);
                $lines = $d['lines'] ?? [];
                unset($d['lines']);
                $ledger = new StockLedger;
                if (isset($d['unit_id'])) {
                    $unit = $this->unit($m, (int) $d['unit_id'], $ability);
                    unset($d['unit_id']);
                    $field = $m === 'stock-transfers' ? 'source_unit_id' : 'operational_unit_id';
                    if ($r->exists && (int) $r->$field !== $unit) {
                        StockLedger::fail('Unit dokumen tidak dapat diganti.');
                    }
                    $d[$field] = $unit;
                }
                if ($m === 'sales-orders') {
                    [$d,$lines] = (new PointOfSale)->prepare($input, $r->exists ? $r : null);
                    $this->unit($m, $d['operational_unit_id'], $ability);
                    if (! $r->exists) {
                        $d += ['number' => 'SO-'.Str::ulid(), 'status' => 'DRAFT', 'ordered_at' => now(), 'cashier_id' => auth()->id()];
                    }
                } elseif ($m === 'customers') {
                    $d['organization_id'] = auth()->user()->organization_id;
                } elseif ($m === 'cash-registers') {
                    if (CashRegister::where('operational_unit_id', $unit)->whereRaw('LOWER(code) = ?', [strtolower($d['code'])])->where('id', '!=', $r->id ?? 0)->exists()) {
                        StockLedger::fail('Kode kasir sudah digunakan.', 'code');
                    }
                    if ($r->exists && $r->registerSessions()->where('status', 'OPEN')->exists()) {
                        StockLedger::fail('Tutup sesi aktif sebelum mengubah kasir.');
                    }
                } elseif ($m === 'register-sessions') {
                    $register = CashRegister::whereIn('operational_unit_id', TradeAccess::units(auth()->user(), $m)->select('id'))->where('is_active', true)->lockForUpdate()->find($d['cash_register_id']);
                    if (! $register) {
                        StockLedger::fail('Kasir tidak tersedia.', 'cash_register_id');
                    }
                    $this->unit($m, $register->operational_unit_id, 'create');
                    (new PointOfSale)->openDay($register->operational_unit_id);
                    if ($register->registerSessions()->where('status', 'OPEN')->exists()) {
                        StockLedger::fail('Kasir masih memiliki sesi terbuka.');
                    }
                    $d += ['opened_by' => auth()->id(), 'opened_at' => now(), 'status' => 'OPEN'];
                } elseif ($m === 'inventory-batches') {
                    $item = TradeAccess::items(auth()->user())->where('is_active', true)->lockForUpdate()->find($d['inventory_item_id']);
                    if (! $item) {
                        StockLedger::fail('Item tidak tersedia.', 'inventory_item_id');
                    }
                    if ($item->tracking_policy === 'LOT_EXPIRY' && empty($d['expires_on'])) {
                        StockLedger::fail('Tanggal kedaluwarsa wajib.', 'expires_on');
                    }
                    if (! empty($d['manufactured_on']) && $d['manufactured_on'] > $d['received_on']) {
                        StockLedger::fail('Tanggal produksi tidak boleh setelah penerimaan.', 'manufactured_on');
                    }
                    if (InventoryBatch::where('inventory_item_id', $item->id)->whereRaw('LOWER(batch_no) = ?', [strtolower($d['batch_no'])])->exists()) {
                        StockLedger::fail('Nomor batch sudah digunakan.', 'batch_no');
                    }
                    $d['qc_status'] = 'RELEASED';
                } elseif (in_array($m, ['inventory-movements', 'inventory-reservations', 'waste-records'], true)) {
                    $metadata = [];
                    if ($m === 'inventory-movements') {
                        $canonical = $d;
                        ksort($canonical);
                        $hash = hash('sha256', json_encode($canonical));
                        $prior = AuditLog::where('organization_id', auth()->user()->organization_id)->where('event', 'inventory_movements.post')->where('new_values->_submission_token', $input['submission_token'])->first();
                        if ($prior) {
                            if (($prior->new_values['_payload_hash'] ?? null) !== $hash) {
                                StockLedger::fail('Form sudah diposting dengan isi berbeda. Muat form baru.');
                            }

                            return TradeAccess::query(auth()->user(), $m)->findOrFail($prior->auditable_id);
                        }
                        $metadata = ['_submission_token' => $input['submission_token'], '_payload_hash' => $hash];
                    }
                    $item = $ledger->item($unit, (int) $d['inventory_item_id']);
                    $batch = $ledger->batch($item, $d['inventory_batch_id'] ?? null, $m !== 'waste-records');
                    StockLedger::precision($item, (string) $d['quantity']);
                    if ($m === 'inventory-movements') {
                        $r = $ledger->post($unit, $item->id, $batch?->id, bcmul((string) $d['quantity'], $d['direction'] === 'IN' ? '1' : '-1', 4), 'ADJUSTMENT', null, $d['reason']);
                        $this->audit($r, $m, 'post', [], $metadata);

                        return $r;
                    }
                    if ($m === 'inventory-reservations') {
                        $balance = $ledger->balance($unit, $item->id, $batch?->id);
                        if (bccomp($ledger->available($balance), (string) $d['quantity'], 4) < 0) {
                            StockLedger::fail('Stok tersedia tidak cukup untuk reservasi.');
                        }
                        $balance->update(['reserved' => StockLedger::quantity(bcadd((string) $balance->reserved, (string) $d['quantity'], 4))]);
                        $d['status'] = 'ACTIVE';
                    } else {
                        $d += ['status' => 'REPORTED', 'reported_by' => auth()->id(), 'occurred_at' => now(), 'unit_cost' => $batch?->unit_cost ?? $item->standard_cost, 'total_cost' => StockLedger::quantity(bcmul((string) $d['quantity'], $batch?->unit_cost ?? $item->standard_cost, 4))];
                    }
                } elseif (in_array($m, ['stock-transfers', 'stock-counts'], true)) {
                    if ($m === 'stock-transfers') {
                        $d['destination_unit_id'] = $this->unit($m, (int) $d['destination_id'], $ability);
                        unset($d['destination_id']);
                        if ($unit === $d['destination_unit_id']) {
                            StockLedger::fail('Unit asal dan tujuan harus berbeda.');
                        }
                        if ($r->exists && (int) $r->destination_unit_id !== $d['destination_unit_id']) {
                            StockLedger::fail('Unit tujuan tidak dapat diganti.');
                        }
                        $d['organization_id'] = auth()->user()->organization_id;
                        if (! $r->exists) {
                            $d += ['number' => 'TR-'.Str::ulid(), 'status' => 'DRAFT', 'requested_by' => auth()->id(), 'requested_at' => now()];
                        }
                    } else {
                        $d['counted_at'] = now();
                        if (! $r->exists) {
                            $d += ['number' => 'SC-'.Str::ulid(), 'status' => 'DRAFT', 'counted_by' => auth()->id()];
                        }
                    }
                    $prepared = [];
                    $seen = [];
                    foreach ($lines as $line) {
                        $item = $ledger->item($unit, (int) $line['inventory_item_id']);
                        $batch = $ledger->batch($item, $line['inventory_batch_id'] ?? null, $m === 'stock-transfers');
                        $key = $item->id.':'.$batch?->id;
                        if (isset($seen[$key])) {
                            StockLedger::fail('Item/batch yang sama tidak boleh diulang.', 'lines');
                        }$seen[$key] = true;
                        $qty = (string) $line[$m === 'stock-transfers' ? 'requested_quantity' : 'physical_quantity'];
                        StockLedger::precision($item, $qty);
                        if ($m === 'stock-transfers') {
                            $ledger->item($d['destination_unit_id'], $item->id);
                            $line['uom_id'] = $item->base_uom_id;
                        } else {
                            $balance = $ledger->balance($unit, $item->id, $batch?->id);
                            $line['system_quantity'] = $balance->on_hand;
                            $line['variance_quantity'] = bcsub($qty, $balance->on_hand, 4);
                            $line['variance_cost'] = StockLedger::quantity(bcmul($line['variance_quantity'], $batch?->unit_cost ?? $item->standard_cost, 4));
                            if (bccomp($line['variance_quantity'], '0', 4) !== 0 && empty($line['reason'])) {
                                StockLedger::fail('Selisih opname memerlukan alasan.', 'lines');
                            }
                        }
                        $prepared[] = $line;
                    } $lines = $prepared;
                } elseif ($m === 'sales-refunds') {
                    $order = SalesOrder::where('organization_id', auth()->user()->organization_id)->whereIn('operational_unit_id', TradeAccess::units(auth()->user(), $m)->select('id'))->whereIn('status', ['COMPLETED', 'PARTIALLY_REFUNDED'])->lockForUpdate()->find($d['sales_order_id']);
                    if (! $order) {
                        StockLedger::fail('Penjualan tidak tersedia untuk refund.', 'sales_order_id');
                    }
                    $this->unit($m, $order->operational_unit_id, 'create');
                    $allocated = '0';
                    foreach ($order->salesRefunds()->whereIn('status', ['PENDING', 'REFUNDED'])->get() as $refund) {
                        $allocated = bcadd($allocated, $refund->amount, 2);
                    }
                    if (bccomp($d['amount'], '0', 2) <= 0 || bccomp(bcadd($allocated, $d['amount'], 2), $order->total_amount, 2) > 0) {
                        StockLedger::fail('Nominal refund melebihi sisa penjualan.', 'amount');
                    }
                    $d += ['number' => 'RF-'.Str::ulid(), 'status' => 'PENDING', 'requested_by' => auth()->id()];
                } elseif ($m === 'payment-settlements') {
                    if (! empty($d['sales_channel_id']) && ! SalesChannel::where('organization_id', auth()->user()->organization_id)->find($d['sales_channel_id'])) {
                        StockLedger::fail('Channel tidak tersedia.');
                    }
                    if ($d['period_end'] > now(self::timezone())->format('Y-m-d')) {
                        StockLedger::fail('Periode settlement tidak boleh di masa depan.');
                    }
                    if (PaymentSettlement::where('operational_unit_id', $unit)->where('sales_channel_id', $d['sales_channel_id'] ?? null)->where('payment_method', $d['payment_method'])->where('status', '!=', 'CANCELLED')->where('id', '!=', $r->id ?? 0)->whereDate('period_start', '<=', $d['period_end'])->whereDate('period_end', '>=', $d['period_start'])->exists()) {
                        StockLedger::fail('Periode settlement tumpang tindih.');
                    }
                    [$totals] = (new PointOfSale)->payments($unit, $d['period_start'], $d['period_end'], $d['sales_channel_id'] ?? null, true);
                    $d['expected_amount'] = $totals[$d['payment_method']] ?? '0';
                    $d['variance_amount'] = bcsub(bcadd($d['settled_amount'], $d['fee_amount'], 2), $d['expected_amount'], 2);
                    $d['status'] = 'DRAFT';
                } elseif (in_array($m, ['daily-closings', 'inventory-reconciliations'], true)) {
                    if ($d['business_date'] > now(self::timezone())->format('Y-m-d')) {
                        StockLedger::fail('Tanggal bisnis tidak boleh di masa depan.');
                    }
                    if ($r->exists && $r->business_date->format('Y-m-d') !== $d['business_date']) {
                        StockLedger::fail('Tanggal bisnis tidak dapat diganti.');
                    }
                    $d['status'] = 'DRAFT';
                    if ($m === 'inventory-reconciliations') {
                        $d['prepared_by'] = auth()->id();
                    }
                }
                $actuals = null;
                if ($m === 'daily-closings') {
                    $actuals = ['CASH' => $d['actual_cash'], 'CARD' => $d['card_actual'], 'QRIS' => $d['qris_actual'], 'TRANSFER' => $d['transfer_actual']];
                    $d['non_cash_actual'] = StockLedger::signedMoney(bcadd(bcadd($d['card_actual'], $d['qris_actual'], 2), $d['transfer_actual'], 2));
                    unset($d['card_actual'], $d['qris_actual'], $d['transfer_actual']);
                }
                $r->fill($d)->save();
                if ($rel = TradeDefinition::relation($m)) {
                    if (TradeDefinition::lineFields($m)) {
                        $r->$rel()->delete();
                        $r->$rel()->createMany($lines);
                    }
                    if ($m === 'daily-closings') {
                        $this->closing($r, $actuals);
                    }
                    if ($m === 'inventory-reconciliations') {
                        $this->reconcile($r);
                    }
                }
                $this->audit($r, $m, $event, $before);

                return $r;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            StockLedger::fail('Data atau tanggal bisnis sudah digunakan.');
        }
    }

    public function action(Request $request, string $m, Model $record, string $action, array $data = []): void
    {
        DB::transaction(function () use ($m, $record, $action, $data) {
            $this->lock();
            $r = TradeAccess::query(auth()->user(), $m)->lockForUpdate()->findOrFail($record->id);
            Gate::authorize('trade-action', [$m, $r, $action]);
            $before = $r->attributesToArray();
            if ($r->exists && ($relation = TradeDefinition::relation($m))) {
                $before['lines'] = $r->$relation()->get()->toArray();
            }
            $ledger = new StockLedger;
            if ($action === 'delete') {
                if ($m === 'customers' && $r->salesOrders()->withTrashed()->exists()) {
                    StockLedger::fail('Customer sudah digunakan dalam penjualan.');
                }
                if ($m === 'cash-registers' && $r->registerSessions()->exists()) {
                    StockLedger::fail('Kasir sudah memiliki sesi.');
                }
                $r->delete();
            } elseif ($action === 'checkout') {
                (new PointOfSale)->checkout($r, $data);
            } elseif ($action === 'void') {
                $r->update(['status' => 'VOID']);
            } elseif ($action === 'cancel') {
                $r->update(['status' => 'CANCELLED']);
            } elseif ($action === 'reject') {
                $r->update(['status' => 'REJECTED', 'approved_by' => auth()->id()]);
            } elseif ($action === 'activate' || $action === 'deactivate') {
                if ($r->registerSessions()->where('status', 'OPEN')->exists()) {
                    StockLedger::fail('Tutup sesi kasir aktif terlebih dahulu.');
                }
                $r->update(['is_active' => $action === 'activate']);
            } elseif ($action === 'close') {
                if ($r->salesOrders()->where('status', 'DRAFT')->exists()) {
                    StockLedger::fail('Selesaikan atau void seluruh draft dalam sesi.');
                }
                $r->update(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => auth()->id(), 'closing_cash' => $data['closing_cash']]);
            } elseif ($action === 'release') {
                $balance = $ledger->balance($r->operational_unit_id, $r->inventory_item_id, $r->inventory_batch_id);
                if (bccomp($balance->reserved, $r->quantity, 4) < 0) {
                    StockLedger::fail('Saldo reservasi tidak konsisten.');
                }
                $balance->update(['reserved' => bcsub($balance->reserved, $r->quantity, 4)]);
                $r->update(['status' => 'RELEASED']);
            } elseif ($action === 'dispatch' || $action === 'receive') {
                $lines = $r->stockTransferLines()->lockForUpdate()->get();
                if ($lines->isEmpty()) {
                    StockLedger::fail('Transfer memerlukan baris barang.');
                }
                foreach ($lines as $line) {
                    $target = $ledger->balance($r->destination_unit_id, $line->inventory_item_id, $line->inventory_batch_id);
                    if ($action === 'dispatch') {
                        $ledger->item($r->destination_unit_id, $line->inventory_item_id);
                        $ledger->post($r->source_unit_id, $line->inventory_item_id, $line->inventory_batch_id, bcmul($line->requested_quantity, '-1', 4), 'TRANSFER_OUT', $r);
                        $target->update(['in_transit' => StockLedger::quantity(bcadd((string) $target->in_transit, $line->requested_quantity, 4))]);
                        $line->update(['picked_quantity' => $line->requested_quantity, 'dispatched_quantity' => $line->requested_quantity]);
                    } else {
                        if (bccomp($target->in_transit, $line->dispatched_quantity, 4) < 0) {
                            StockLedger::fail('Saldo transit tidak konsisten.');
                        }
                        $outgoing = InventoryMovement::where('organization_id', auth()->user()->organization_id)->where('reference_type', $r->getMorphClass())->where('reference_id', $r->id)->where('movement_type', 'TRANSFER_OUT')->where('inventory_item_id', $line->inventory_item_id)->where('inventory_batch_id', $line->inventory_batch_id)->firstOrFail();
                        $ledger->post($r->destination_unit_id, $line->inventory_item_id, $line->inventory_batch_id, $line->dispatched_quantity, 'TRANSFER_IN', $r, '', false, $outgoing->unit_cost);
                        $target->refresh()->update(['in_transit' => bcsub($target->in_transit, $line->dispatched_quantity, 4)]);
                        $line->update(['received_quantity' => $line->dispatched_quantity, 'variance_quantity' => '0']);
                    }
                }
                $r->update($action === 'dispatch' ? ['status' => 'DISPATCHED', 'dispatched_at' => now(), 'dispatched_by' => auth()->id(), 'picked_by' => auth()->id()] : ['status' => 'RECEIVED', 'received_at' => now(), 'received_by' => auth()->id()]);
            } elseif ($action === 'settle') {
                // Freeze only periods whose business days have been approved and closed.
                $day = CarbonImmutable::parse($r->period_start);
                $end = CarbonImmutable::parse($r->period_end);
                if ($day->diffInDays($end) > 366) {
                    StockLedger::fail('Periode settlement maksimal 366 hari.');
                }
                for (; $day <= $end; $day = $day->addDay()) {
                    if (! DailyClosing::where('operational_unit_id', $r->operational_unit_id)->whereDate('business_date', $day->format('Y-m-d'))->where('status', 'APPROVED')->exists()) {
                        StockLedger::fail('Seluruh tanggal settlement harus memiliki daily closing yang disetujui.');
                    }
                }
                [$totals] = (new PointOfSale)->payments($r->operational_unit_id, $r->period_start->format('Y-m-d'), $r->period_end->format('Y-m-d'), $r->sales_channel_id, true);
                $expected = $totals[$r->payment_method] ?? '0';
                $r->update(['expected_amount' => $expected, 'variance_amount' => bcsub(bcadd($r->settled_amount, $r->fee_amount, 2), $expected, 2), 'status' => 'SETTLED', 'settled_at' => now()]);
            } elseif ($action === 'approve') {
                if ($m === 'sales-refunds') {
                    (new PointOfSale)->refund($r);
                } elseif ($m === 'waste-records') {
                    $ledger->post($r->operational_unit_id, $r->inventory_item_id, $r->inventory_batch_id, bcmul($r->quantity, '-1', 4), 'WASTE', $r, $r->notes ?? '', false);
                    $r->update(['status' => 'APPROVED', 'approved_by' => auth()->id()]);
                } elseif ($m === 'stock-counts') {
                    foreach ($r->stockCountLines()->lockForUpdate()->get() as $line) {
                        $b = $ledger->balance($r->operational_unit_id, $line->inventory_item_id, $line->inventory_batch_id);
                        if (bccomp($b->on_hand, $line->system_quantity, 4) !== 0) {
                            StockLedger::fail('Saldo berubah sejak opname. Edit dan simpan ulang hitungan fisik.');
                        }
                        if (bccomp($line->variance_quantity, '0', 4) !== 0) {
                            $ledger->post($r->operational_unit_id, $line->inventory_item_id, $line->inventory_batch_id, $line->variance_quantity, 'STOCK_COUNT', $r, $line->reason ?? '', false);
                        }
                        $b->refresh()->update(['last_counted_at' => now()]);
                    } $r->update(['status' => 'APPROVED', 'approved_by' => auth()->id()]);
                } elseif ($m === 'daily-closings') {
                    if (RegisterSession::where('status', 'OPEN')->whereHas('cashRegister', fn ($q) => $q->where('operational_unit_id', $r->operational_unit_id))->exists()) {
                        StockLedger::fail('Tutup seluruh sesi kasir unit sebelum daily closing.');
                    }
                    $this->closing($r);
                    $r->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'closed_by' => auth()->id(), 'closed_at' => now()]);
                } elseif ($m === 'inventory-reconciliations') {
                    $this->reconcile($r);
                    $r->update(['status' => 'APPROVED', 'approved_by' => auth()->id()]);
                }
            }
            $this->audit($r, $m, $action, $before);
        }, 3);
    }

    private function closing(Model $r, ?array $actuals = null): void
    {
        [$totals,$refunds,$from,$until] = (new PointOfSale)->payments($r->operational_unit_id, $r->business_date->format('Y-m-d'), $r->business_date->format('Y-m-d'));
        $orders = SalesOrder::where('organization_id', auth()->user()->organization_id)->where('operational_unit_id', $r->operational_unit_id)->whereIn('status', ['COMPLETED', 'PARTIALLY_REFUNDED', 'REFUNDED'])->where('ordered_at', '>=', $from)->where('ordered_at', '<', $until)->get();
        $gross = '0';
        $discount = '0';
        $net = '0';
        $refundTotal = '0';
        $nonCash = '0';
        foreach ($orders as $order) {
            $gross = bcadd($gross, $order->subtotal, 2);
            $discount = bcadd($discount, $order->discount_amount, 2);
            $net = bcadd($net, $order->total_amount, 2);
        }
        foreach ($refunds as $refund) {
            $refundTotal = bcadd($refundTotal, $refund->amount, 2);
        }
        foreach ($totals as $method => $amount) {
            if ($method !== 'CASH') {
                $nonCash = bcadd($nonCash, $amount, 2);
            }
        }
        $cash = $totals['CASH'];
        $variance = bcsub($r->actual_cash, $cash, 2);
        if ((bccomp($variance, '0', 2) !== 0 || bccomp($nonCash, $r->non_cash_actual, 2) !== 0) && ! $r->variance_reason) {
            StockLedger::fail('Selisih closing memerlukan penjelasan.', 'variance_reason');
        }
        $r->update(['gross_sales' => $gross, 'discounts' => $discount, 'refunds' => $refundTotal, 'net_sales' => bcsub($net, $refundTotal, 2), 'expected_cash' => $cash, 'non_cash_expected' => $nonCash, 'cash_variance' => $variance]);
        $actuals ??= $r->dailyClosingPayments()->pluck('actual_amount', 'payment_method')->all();
        $r->dailyClosingPayments()->delete();
        foreach ($totals as $method => $amount) {
            $r->dailyClosingPayments()->create(['payment_method' => $method, 'expected_amount' => $amount, 'actual_amount' => $actuals[$method] ?? '0', 'variance_amount' => bcsub($actuals[$method] ?? '0', $amount, 2)]);
        }
    }

    private function reconcile(Model $r): void
    {
        $from = CarbonImmutable::parse($r->business_date->format('Y-m-d'), self::timezone())->startOfDay()->utc();
        $until = $from->setTimezone(self::timezone())->addDay()->utc();
        $ledger = InventoryMovement::where('organization_id', auth()->user()->organization_id)->where('operational_unit_id', $r->operational_unit_id)->where('occurred_at', '<', $until)->orderBy('occurred_at')->orderBy('id')->get()->groupBy('inventory_item_id');
        $r->inventoryReconciliationLines()->delete();
        $total = '0';
        foreach ($ledger as $itemId => $movements) {
            $opening = '0';
            $net = '0';
            $received = '0';
            $sold = '0';
            $waste = '0';
            foreach ($movements as $move) {
                if ($move->occurred_at < $from) {
                    $opening = bcadd($opening, $move->quantity, 4);

                    continue;
                }
                $net = bcadd($net, $move->quantity, 4);
                if (bccomp($move->quantity, '0', 4) > 0) {
                    $received = bcadd($received, $move->quantity, 4);
                }
                if ($move->movement_type === 'SALE') {
                    $sold = bcsub($sold, $move->quantity, 4);
                }
                if ($move->movement_type === 'WASTE') {
                    $waste = bcsub($waste, $move->quantity, 4);
                }
            }
            $expected = bcadd($opening, $net, 4);
            $counts = StockCountLine::where('inventory_item_id', $itemId)->whereHas('stockCount', fn ($q) => $q->where('operational_unit_id', $r->operational_unit_id)->where('status', 'APPROVED')->where('counted_at', '>=', $from)->where('counted_at', '<', $until))->get();
            if ($counts->isEmpty()) {
                StockLedger::fail('Rekonsiliasi memerlukan opname disetujui untuk setiap item pada tanggal tersebut.');
            }
            $expectedBatches = $movements->pluck('inventory_batch_id')->map(fn ($id) => (string) ($id ?? 0))->unique()->sort()->values()->all();
            $countedBatches = $counts->pluck('inventory_batch_id')->map(fn ($id) => (string) ($id ?? 0))->unique()->sort()->values()->all();
            if ($expectedBatches !== $countedBatches) {
                StockLedger::fail('Opname harus mencakup seluruh batch item pada ledger.');
            }
            $countedAt = $counts->first()->stockCount->counted_at;
            if ($movements->contains(fn ($move) => $move->movement_type !== 'STOCK_COUNT' && $move->occurred_at > $countedAt)) {
                StockLedger::fail('Ada pergerakan setelah opname. Perbarui opname sebelum rekonsiliasi.');
            }
            $countIds = $counts->pluck('stock_count_id')->unique();
            if ($countIds->count() > 1) {
                StockLedger::fail('Gunakan satu dokumen opname lengkap per item/tanggal rekonsiliasi.');
            }
            $physical = '0';
            foreach ($counts as $line) {
                $physical = bcadd($physical, $line->physical_quantity, 4);
            }
            $variance = bcsub($physical, $expected, 4);
            $item = TradeAccess::items(auth()->user())->findOrFail($itemId);
            $total = bcadd($total, bcmul($variance, $item->standard_cost, 4), 4);
            $r->inventoryReconciliationLines()->create(['inventory_item_id' => $itemId, 'opening_quantity' => $opening, 'received_quantity' => $received, 'sold_quantity' => $sold, 'waste_quantity' => $waste, 'expected_closing_quantity' => $expected, 'physical_closing_quantity' => $physical, 'variance_quantity' => $variance, 'reason' => 'Ringkasan ledger; tidak memposting penyesuaian tambahan.']);
        }
        if ($ledger->isEmpty()) {
            StockLedger::fail('Belum ada ledger stok untuk direkonsiliasi.');
        }
        $r->update(['total_variance_cost' => bcadd($total, '0', 2)]);
    }
}
