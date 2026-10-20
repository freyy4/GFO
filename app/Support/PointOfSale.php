<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\DailyClosing;
use App\Models\ProductVariant;
use App\Models\RegisterSession;
use App\Models\SalesChannel;
use App\Models\SalesOrder;
use App\Models\SalesPayment;
use App\Models\SalesRefund;
use App\Models\UnitProductPrice;
use Carbon\CarbonImmutable;

class PointOfSale
{
    public function session(int $id): RegisterSession
    {
        $session = RegisterSession::where('status', 'OPEN')->where('opened_by', auth()->id())->whereHas('cashRegister', fn ($q) => $q->where('is_active', true)->whereIn('operational_unit_id', TradeAccess::units(auth()->user(), 'sales-orders')->select('id')))->lockForUpdate()->find($id);
        if (! $session) {
            StockLedger::fail('Pilih sesi kasir aktif milik Anda.', 'register_session_id');
        }

        return $session;
    }

    public function openDay(int $unit, ?string $date = null): void
    {
        $date ??= now(TradeMutation::timezone())->format('Y-m-d');
        if (DailyClosing::where('operational_unit_id', $unit)->whereDate('business_date', $date)->where('status', 'APPROVED')->exists()) {
            StockLedger::fail('Tanggal bisnis ini sudah ditutup.');
        }
    }

    public function prepare(array $data, ?SalesOrder $order = null): array
    {
        $session = $this->session($data['register_session_id']);
        $unit = $session->cashRegister->operationalUnit;
        if (! $unit->brand_id || ! $unit->brand || $unit->brand->organization_id !== auth()->user()->organization_id || $unit->brand->status !== 'ACTIVE') {
            StockLedger::fail('Unit kasir memerlukan brand aktif.');
        }
        if ($order && $order->register_session_id !== $session->id) {
            StockLedger::fail('Sesi kasir draft tidak dapat diganti.');
        }
        $this->openDay($unit->id);
        if (! empty($data['customer_id']) && ! Customer::where('organization_id', auth()->user()->organization_id)->find($data['customer_id'])) {
            StockLedger::fail('Customer tidak tersedia.', 'customer_id');
        }
        if (! empty($data['sales_channel_id']) && ! SalesChannel::where('organization_id', auth()->user()->organization_id)->where('type', 'POS')->where('is_active', true)->find($data['sales_channel_id'])) {
            StockLedger::fail('Channel POS tidak tersedia.', 'sales_channel_id');
        }
        $lines = [];
        $seen = [];
        $subtotal = '0';
        foreach ($data['lines'] as $line) {
            if (bccomp((string) $line['quantity'], '0', 4) <= 0) {
                StockLedger::fail('Kuantitas penjualan harus positif.', 'lines');
            }
            $variant = ProductVariant::where('is_active', true)->whereHas('product', fn ($q) => $q->where('brand_id', $unit->brand_id)->where('is_active', true))->lockForUpdate()->find($line['product_variant_id']);
            if (! $variant || ! $variant->inventory_item_id) {
                StockLedger::fail('Varian aktif harus terhubung ke item stok.', 'lines');
            }
            if (isset($seen[$variant->id])) {
                StockLedger::fail('Varian yang sama hanya boleh satu baris.', 'lines');
            } $seen[$variant->id] = true;
            $item = (new StockLedger)->item($unit->id, $variant->inventory_item_id);
            StockLedger::precision($item, (string) $line['quantity']);
            $prices = UnitProductPrice::where('operational_unit_id', $unit->id)->where('product_variant_id', $variant->id)->where('is_active', true)->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->lockForUpdate()->get();
            if ($prices->count() > 1) {
                StockLedger::fail('Terdapat harga unit aktif yang tumpang tindih.');
            }
            $price = $prices->first()?->price ?? $variant->selling_price;
            $total = StockLedger::money(bcmul($price, (string) $line['quantity'], 8));
            $subtotal = bcadd($subtotal, $total, 2);
            $lines[] = ['product_variant_id' => $variant->id, 'item_name_snapshot' => $variant->product->name.' · '.$variant->name, 'quantity' => $line['quantity'], 'unit_price' => $price, 'line_total' => $total];
        }

        return [['organization_id' => auth()->user()->organization_id, 'brand_id' => $unit->brand_id, 'operational_unit_id' => $unit->id, 'register_session_id' => $session->id,
            'customer_id' => $data['customer_id'] ?? null, 'sales_channel_id' => $data['sales_channel_id'] ?? null, 'subtotal' => StockLedger::money($subtotal), 'total_amount' => StockLedger::money($subtotal)], $lines];
    }

    public function checkout(SalesOrder $order, array $data): void
    {
        $oldLines = $order->salesOrderItems()->lockForUpdate()->get();
        [$header,$lines] = $this->prepare(['register_session_id' => $order->register_session_id, 'customer_id' => $order->customer_id, 'sales_channel_id' => $order->sales_channel_id,
            'lines' => $oldLines->map(fn ($l) => $l->only(['product_variant_id', 'quantity']))->all()], $order);
        if ($oldLines->isEmpty()) {
            StockLedger::fail('Penjualan memerlukan item.');
        }
        if (bccomp($header['total_amount'], $order->total_amount, 2) !== 0) {
            StockLedger::fail('Harga berubah. Edit dan simpan draft untuk memperbarui harga sebelum membayar.');
        }
        foreach ($oldLines as $i => $l) {
            if (bccomp($l->unit_price, $lines[$i]['unit_price'], 2) !== 0) {
                StockLedger::fail('Harga item berubah. Simpan ulang draft.');
            }
        }
        if (bccomp($order->total_amount, '0', 2) <= 0) {
            StockLedger::fail('Total penjualan harus lebih dari nol.');
        }
        $cash = $data['payment_method'] === 'CASH';
        $compare = bccomp($data['tendered_amount'], $order->total_amount, 2);
        if ($compare < 0 || (! $cash && $compare !== 0)) {
            StockLedger::fail('Nominal pembayaran tidak sesuai total.', 'tendered_amount');
        }
        if (! $cash && empty($data['reference_no'])) {
            StockLedger::fail('Referensi transaksi wajib untuk pembayaran non-tunai.', 'reference_no');
        }
        if (! $cash && SalesPayment::where('method', $data['payment_method'])->where('reference_no', $data['reference_no'])->whereHas('salesOrder', fn ($q) => $q->where('organization_id', $order->organization_id))->exists()) {
            StockLedger::fail('Referensi pembayaran sudah dipakai.', 'reference_no');
        }
        $cogs = '0';
        $ledger = new StockLedger;
        foreach ($oldLines as $line) {
            $variant = ProductVariant::findOrFail($line->product_variant_id);
            $cost = $ledger->consume($order->operational_unit_id, $variant->inventory_item_id, $line->quantity, $order);
            $line->update(['unit_cogs' => bcdiv($cost, $line->quantity, 4)]);
            $cogs = bcadd($cogs, $cost, 4);
        }
        $order->salesPayments()->create(['method' => $data['payment_method'], 'reference_no' => $data['reference_no'] ?? null, 'amount' => $order->total_amount, 'status' => 'PAID', 'paid_at' => now(),
            'gateway_payload' => ['source' => 'MANUAL', 'tendered_amount' => $data['tendered_amount'], 'change' => bcsub($data['tendered_amount'], $order->total_amount, 2)]]);
        $order->update(['status' => 'COMPLETED', 'cashier_id' => auth()->id(), 'ordered_at' => now(), 'cogs_amount' => StockLedger::money($cogs)]);
        if ($order->customer_id) {
            Customer::whereKey($order->customer_id)->where('organization_id', $order->organization_id)->update(['last_order_at' => now()]);
        }
    }

    public function refund(SalesRefund $refund): void
    {
        $order = SalesOrder::where('organization_id', auth()->user()->organization_id)->lockForUpdate()->findOrFail($refund->sales_order_id);
        if (! in_array($order->status, ['COMPLETED', 'PARTIALLY_REFUNDED'], true)) {
            StockLedger::fail('Penjualan tidak dapat direfund.');
        }
        $this->openDay($order->operational_unit_id);
        if ($order->salesPayments()->where('status', 'PAID')->count() !== 1) {
            StockLedger::fail('Refund otomatis hanya mendukung satu pembayaran per penjualan.');
        }
        $total = '0';
        foreach ($order->salesRefunds()->where('status', 'REFUNDED')->lockForUpdate()->get() as $r) {
            $total = bcadd($total, $r->amount, 2);
        }
        $total = bcadd($total, $refund->amount, 2);
        if (bccomp($total, $order->total_amount, 2) > 0) {
            StockLedger::fail('Total refund melebihi nilai penjualan.');
        }
        $refund->update(['status' => 'REFUNDED', 'approved_by' => auth()->id(), 'refunded_at' => now()]);
        $order->update(['status' => bccomp($total, $order->total_amount, 2) === 0 ? 'REFUNDED' : 'PARTIALLY_REFUNDED']);
    }

    public function payments(int $unit, string $start, string $end, ?int $channel = null, bool $filterChannel = false): array
    {
        $from = CarbonImmutable::parse($start, TradeMutation::timezone())->startOfDay()->utc();
        $until = CarbonImmutable::parse($end, TradeMutation::timezone())->addDay()->startOfDay()->utc();
        $orders = fn ($q) => $q->where('organization_id', auth()->user()->organization_id)->where('operational_unit_id', $unit)->when($filterChannel, fn ($q) => $q->where('sales_channel_id', $channel));
        $payments = SalesPayment::where('status', 'PAID')->where('paid_at', '>=', $from)->where('paid_at', '<', $until)->whereHas('salesOrder', $orders)->get();
        $totals = ['CASH' => '0.00', 'CARD' => '0.00', 'QRIS' => '0.00', 'TRANSFER' => '0.00'];
        foreach ($payments as $p) {
            $totals[$p->method] = bcadd($totals[$p->method] ?? '0', $p->amount, 2);
        }
        $refunds = SalesRefund::where('status', 'REFUNDED')->where('refunded_at', '>=', $from)->where('refunded_at', '<', $until)->whereHas('salesOrder', $orders)->with('salesOrder.salesPayments')->get();
        foreach ($refunds as $r) {
            $paid = $r->salesOrder->salesPayments->where('status', 'PAID');
            if ($paid->count() !== 1) {
                StockLedger::fail('Refund dengan pembayaran terbagi perlu rekonsiliasi manual.');
            }
            $method = $paid->first()->method;
            $totals[$method] = bcsub($totals[$method] ?? '0', $r->amount, 2);
        }

        return [$totals, $refunds, $from, $until];
    }
}
