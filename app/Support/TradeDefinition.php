<?php

namespace App\Support;

use App\Models;

class TradeDefinition
{
    public const MODELS = [
        'inventory' => Models\InventoryBalance::class, 'inventory-balances' => Models\InventoryBalance::class,
        'inventory-batches' => Models\InventoryBatch::class, 'inventory-reservations' => Models\InventoryReservation::class,
        'inventory-movements' => Models\InventoryMovement::class, 'stock-transfers' => Models\StockTransfer::class,
        'stock-counts' => Models\StockCount::class, 'inventory-reconciliations' => Models\InventoryReconciliation::class, 'waste-records' => Models\WasteRecord::class,
        'sales-orders' => Models\SalesOrder::class, 'sales-payments' => Models\SalesPayment::class, 'sales-refunds' => Models\SalesRefund::class,
        'payment-settlements' => Models\PaymentSettlement::class, 'cash-registers' => Models\CashRegister::class,
        'register-sessions' => Models\RegisterSession::class, 'daily-closings' => Models\DailyClosing::class, 'customers' => Models\Customer::class,
    ];

    public const LABELS = ['inventory' => 'Ringkasan Stok', 'inventory-balances' => 'Stok per Lokasi', 'inventory-batches' => 'Batch & Kedaluwarsa',
        'inventory-reservations' => 'Reservasi Stok', 'inventory-movements' => 'Pergerakan Stok', 'stock-transfers' => 'Transfer Stok',
        'stock-counts' => 'Stock Opname', 'inventory-reconciliations' => 'Rekonsiliasi Stok', 'waste-records' => 'Waste & Shrinkage',
        'sales-orders' => 'Penjualan & POS', 'sales-payments' => 'Pembayaran', 'sales-refunds' => 'Refund & Void',
        'payment-settlements' => 'Payment Settlement', 'cash-registers' => 'Cash Register', 'register-sessions' => 'Sesi Kasir',
        'daily-closings' => 'Daily Closing', 'customers' => 'Customer'];

    public static function inventory(string $module): bool
    {
        return in_array($module, array_slice(array_keys(self::MODELS), 0, 9), true);
    }

    public static function readOnly(string $module): bool
    {
        return in_array($module, ['inventory', 'inventory-balances', 'sales-payments'], true);
    }

    // Fields: label, HTML type, required. Foreign references are resolved within authorized scope by the service.
    public static function fields(string $m): array
    {
        $unit = ['Unit operasional', 'select', true];
        $item = ['Item stok', 'select', true];
        $batch = ['Batch (wajib untuk item lot)', 'select', false];

        return match ($m) {
            'inventory-batches' => ['inventory_item_id' => $item, 'batch_no' => ['Nomor batch', 'text', true], 'manufactured_on' => ['Tanggal produksi', 'date', false], 'received_on' => ['Tanggal diterima', 'date', true], 'expires_on' => ['Kedaluwarsa', 'date', false], 'unit_cost' => ['Biaya per satuan', 'decimal', true]],
            'inventory-movements' => ['unit_id' => $unit, 'inventory_item_id' => $item, 'inventory_batch_id' => $batch, 'direction' => ['Arah penyesuaian', 'select', true], 'quantity' => ['Kuantitas', 'decimal', true], 'reason' => ['Alasan penyesuaian stok', 'textarea', true]],
            'inventory-reservations' => ['unit_id' => $unit, 'inventory_item_id' => $item, 'inventory_batch_id' => $batch, 'quantity' => ['Kuantitas reservasi', 'decimal', true]],
            'stock-transfers' => ['unit_id' => ['Unit asal', 'select', true], 'destination_id' => ['Unit tujuan', 'select', true], 'notes' => ['Catatan', 'textarea', false]],
            'stock-counts' => ['unit_id' => $unit],
            'inventory-reconciliations' => ['unit_id' => $unit, 'business_date' => ['Tanggal bisnis', 'date', true]],
            'waste-records' => ['unit_id' => $unit, 'inventory_item_id' => $item, 'inventory_batch_id' => $batch, 'quantity' => ['Kuantitas waste', 'decimal', true], 'reason_code' => ['Kode alasan', 'text', true], 'notes' => ['Keterangan', 'textarea', true]],
            'sales-orders' => ['register_session_id' => ['Sesi kasir milik Anda', 'select', true], 'customer_id' => ['Customer', 'select', false], 'sales_channel_id' => ['Channel POS', 'select', false]],
            'sales-refunds' => ['sales_order_id' => ['Penjualan', 'select', true], 'amount' => ['Nominal refund', 'money', true], 'reason' => ['Alasan refund', 'textarea', true]],
            'cash-registers' => ['unit_id' => $unit, 'code' => ['Kode kasir', 'text', true], 'name' => ['Nama kasir', 'text', true]],
            'register-sessions' => ['cash_register_id' => ['Cash register', 'select', true], 'opening_cash' => ['Modal kas awal', 'money', true]],
            'payment-settlements' => ['unit_id' => $unit, 'sales_channel_id' => ['Channel (kosong: penjualan tanpa channel)', 'select', false], 'payment_method' => ['Metode pembayaran', 'select', true], 'period_start' => ['Awal periode', 'date', true], 'period_end' => ['Akhir periode', 'date', true], 'settled_amount' => ['Dana diterima', 'money', true], 'fee_amount' => ['Biaya settlement', 'money', true], 'reference_no' => ['Referensi', 'text', true]],
            'daily-closings' => ['unit_id' => $unit, 'business_date' => ['Tanggal bisnis', 'date', true], 'actual_cash' => ['Kas penjualan aktual (tanpa modal awal)', 'signed-money', true], 'card_actual' => ['Kartu aktual', 'signed-money', true], 'qris_actual' => ['QRIS aktual', 'signed-money', true], 'transfer_actual' => ['Transfer bank aktual', 'signed-money', true], 'variance_reason' => ['Penjelasan selisih', 'textarea', false]],
            'customers' => ['name' => ['Nama', 'text', true], 'phone' => ['Telepon', 'tel', false], 'email' => ['Email', 'email', false], 'birth_date' => ['Tanggal lahir', 'date', false]],
            default => [],
        };
    }

    public static function lineFields(string $m): array
    {
        return match ($m) {
            'sales-orders' => ['product_variant_id' => ['Varian produk', 'select', true], 'quantity' => ['Kuantitas', 'decimal', true]],
            'stock-transfers' => ['inventory_item_id' => ['Item', 'select', true], 'inventory_batch_id' => ['Batch', 'select', false], 'requested_quantity' => ['Kuantitas', 'decimal', true]],
            'stock-counts' => ['inventory_item_id' => ['Item', 'select', true], 'inventory_batch_id' => ['Batch', 'select', false], 'physical_quantity' => ['Stok fisik', 'decimal', true], 'reason' => ['Alasan selisih', 'text', false]],
            default => [],
        };
    }

    public static function relation(string $m): ?string
    {
        return match ($m) {
            'sales-orders' => 'salesOrderItems','stock-transfers' => 'stockTransferLines','stock-counts' => 'stockCountLines',
            'inventory-reconciliations' => 'inventoryReconciliationLines','daily-closings' => 'dailyClosingPayments',default => null
        };
    }

    public static function actions(string $m): array
    {
        return match ($m) {
            'sales-orders' => ['checkout' => 'Bayar & selesaikan', 'void' => 'Void draft'],
            'sales-refunds' => ['approve' => 'Konfirmasi refund dibayarkan', 'reject' => 'Tolak refund'],
            'stock-transfers' => ['dispatch' => 'Kirim stok', 'receive' => 'Terima seluruh stok', 'cancel' => 'Batalkan'],
            'daily-closings','inventory-reconciliations' => ['approve' => 'Setujui & finalisasi'],
            'stock-counts','waste-records' => ['approve' => 'Setujui & finalisasi', 'cancel' => 'Batalkan'],
            'inventory-reservations' => ['release' => 'Lepaskan reservasi'],
            'payment-settlements' => ['settle' => 'Konfirmasi settlement', 'cancel' => 'Batalkan'],
            'register-sessions' => ['close' => 'Tutup sesi'],
            'cash-registers' => ['activate' => 'Aktifkan', 'deactivate' => 'Nonaktifkan'],default => []
        };
    }
}
