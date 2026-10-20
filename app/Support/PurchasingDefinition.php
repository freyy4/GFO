<?php

namespace App\Support;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\SupplierContract;
use App\Models\SupplierItem;
use App\Models\SupplierPriceHistory;

class PurchasingDefinition
{
    public const LABELS = ['purchase-requirements' => 'Kebutuhan Pembelian', 'purchase-requisitions' => 'Purchase Requisition',
        'purchase-orders' => 'Purchase Order', 'goods-receipts' => 'Penerimaan Barang', 'supplier-contracts' => 'Kontrak Supplier',
        'supplier-price-history' => 'Riwayat Harga Supplier', 'supplier-items' => 'Barang Supplier'];

    public const MODELS = ['purchase-requisitions' => PurchaseRequisition::class, 'purchase-orders' => PurchaseOrder::class,
        'goods-receipts' => GoodsReceipt::class, 'supplier-contracts' => SupplierContract::class,
        'supplier-price-history' => SupplierPriceHistory::class, 'supplier-items' => SupplierItem::class];

    public static function document(string $module): bool
    {
        return in_array($module, ['purchase-requisitions', 'purchase-orders', 'goods-receipts'], true);
    }

    public static function relation(string $module): string
    {
        return match ($module) {
            'purchase-requisitions' => 'purchaseRequisitionLines', 'purchase-orders' => 'purchaseOrderLines', 'goods-receipts' => 'goodsReceiptLines'
        };
    }

    public static function fields(string $module): array
    {
        return match ($module) {
            'purchase-requisitions' => ['destination_unit_id' => ['Unit tujuan', 'select', true], 'required_at' => ['Dibutuhkan pada', 'datetime-local', true], 'notes' => ['Catatan', 'textarea', false]],
            'purchase-orders' => ['supplier_id' => ['Supplier disetujui', 'select', true], 'destination_unit_id' => ['Unit tujuan', 'select', true],
                'purchase_requisition_id' => ['Requisition disetujui (opsional)', 'select', false], 'order_date' => ['Tanggal PO', 'date', true],
                'expected_at' => ['Perkiraan tiba', 'datetime-local', false], 'currency' => ['Mata uang', 'text', true],
                'tax_amount' => ['Pajak nominal', 'decimal', true], 'discount_amount' => ['Diskon nominal', 'decimal', true], 'notes' => ['Catatan', 'textarea', false]],
            'goods-receipts' => ['purchase_order_id' => ['PO yang telah disetujui', 'select', true], 'received_at' => ['Waktu diterima', 'datetime-local', true],
                'supplier_document_no' => ['Nomor dokumen supplier', 'text', false], 'notes' => ['Catatan', 'textarea', false]],
            'supplier-contracts' => ['supplier_id' => ['Supplier', 'select', true], 'contract_no' => ['Nomor kontrak', 'text', true],
                'starts_on' => ['Mulai berlaku', 'date', true], 'ends_on' => ['Akhir berlaku', 'date', false], 'terms' => ['Ketentuan kontrak', 'textarea', false]],
            'supplier-price-history' => ['supplier_item_id' => ['Barang supplier', 'select', true], 'price' => ['Harga per satuan dasar', 'decimal', true],
                'currency' => ['Mata uang', 'text', true], 'effective_from' => ['Berlaku mulai', 'date', true], 'effective_until' => ['Berlaku sampai', 'date', false]],
            'supplier-items' => ['supplier_id' => ['Supplier', 'select', true], 'inventory_item_id' => ['Inventory item', 'select', true],
                'supplier_sku' => ['SKU supplier', 'text', false], 'minimum_order_quantity' => ['Minimum pembelian', 'decimal', true],
                'lead_time_hours' => ['Lead time (jam)', 'number', true]],
        };
    }

    public static function lineFields(string $module): array
    {
        return match ($module) {
            'purchase-requisitions' => ['inventory_item_id' => ['Item', 'select'], 'required_quantity' => ['Jumlah dibutuhkan', 'decimal']],
            'purchase-orders' => ['inventory_item_id' => ['Item', 'select'], 'ordered_quantity' => ['Jumlah dipesan', 'decimal'], 'unit_price' => ['Harga satuan', 'decimal']],
            'goods-receipts' => ['purchase_order_line_id' => ['Baris PO', 'select'], 'received_quantity' => ['Diterima fisik', 'decimal'],
                'accepted_quantity' => ['Lolos QC', 'decimal'], 'rejected_quantity' => ['Ditolak QC', 'decimal'],
                'supplier_batch_no' => ['Batch supplier', 'text'], 'manufactured_on' => ['Tanggal produksi', 'date'], 'expires_on' => ['Kedaluwarsa', 'date'],
                'rejection_reason' => ['Alasan penolakan', 'text']], default => [],
        };
    }
}
