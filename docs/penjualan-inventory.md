# Penjualan & POS dan Inventory

Implementasi menggunakan model/migration yang sudah ada, layout `app`, serta Tailwind/Vite project. Tidak mengubah model, migration, atau modul Purchasing/Produksi.

## Alur operasional

1. Siapkan kasir pada unit dengan brand aktif dan buka sesi kasir. Satu kasir hanya memiliki satu sesi terbuka.
2. Buat draft penjualan memakai sesi sendiri. Pilih varian aktif yang terhubung ke item stok pada brand unit.
3. Harga dihitung server dari harga unit yang berlaku, dengan harga dasar varian sebagai fallback. Harga yang berubah setelah draft dibuat harus diperbarui dengan menyimpan ulang draft.
4. Checkout memposting satu pembayaran dan ledger stok dalam transaksi yang sama. Tunai mendukung kembalian; pembayaran non-tunai memerlukan nominal tepat dan referensi unik dalam organization/metode.
5. Pengambilan stok mengikuti FIFO/FEFO pada item. Batch kedaluwarsa, batch tidak dirilis QC, dan saldo reservasi/karantina tidak dipakai. Satu kuantitas varian sama dengan satu satuan dasar item terkait; tidak ada konsumsi BOM otomatis.
6. Refund diminta lalu dikonfirmasi setelah dana benar-benar dikembalikan. Total refund pending/selesai dibatasi nilai penjualan. Refund tidak memposting stok retur karena skema refund tidak memuat rincian barang retur. Void berlaku untuk draft.
7. Tutup sesi setelah semua draft diselesaikan/void. Daily closing menghitung penjualan, refund, dan penerimaan per metode; aktual kas tidak mencakup modal awal. Aktual boleh negatif pada hari refund melebihi penerimaan.
8. Daily closing yang disetujui mengunci transaksi penjualan baru pada tanggal bisnis tersebut. Settlement final memerlukan closing disetujui untuk seluruh tanggal dalam periodenya; periode tidak boleh tumpang tindih pada kombinasi unit/channel/metode.

## Inventory

- Ringkasan dan saldo per lokasi/batch bersifat baca-saja; saldo berubah melalui ledger.
- Batch dibuat terpisah dan belum menambah saldo. Saldo awal/koreksi masuk melalui penyesuaian stok dengan alasan wajib. Form penyesuaian memiliki token untuk mencegah posting ganda ketika dikirim ulang.
- Reservasi langsung mengurangi stok tersedia; pelepasan mengembalikan ketersediaan tanpa mengubah on-hand.
- Transfer: draft → kirim (stok asal berkurang, transit tujuan bertambah) → terima seluruhnya (transit berkurang, on-hand tujuan bertambah). Unit asal dan tujuan harus sama-sama berada dalam akses pengguna. Jangan konfirmasi jika jumlah fisik berbeda; alur ini belum mendukung penerimaan parsial/selisih transit.
- Opname mengambil snapshot saldo saat draft disimpan. Approval ditolak jika saldo berubah; edit dan simpan ulang hitungan fisik. Selisih wajib memiliki alasan. Approval memposting selisih sekali saja.
- Waste dilaporkan lalu disetujui untuk mengurangi stok; dapat membuang stok karantina tanpa memakai saldo reservasi.
- Rekonsiliasi membandingkan ledger dan opname lengkap yang disetujui, mencakup semua batch pada tanggal bisnis. Tidak membuat posting penyesuaian tambahan. Seluruh saldo awal harus tercatat pada ledger; data lama yang hanya ada di tabel saldo perlu direkonsiliasi terlebih dahulu.
- Posting stok otomatis dari Goods Receipt Purchasing tidak ditambahkan dalam pekerjaan ini.

## Hak akses dan keamanan

Semua query dibatasi organization akun dan unit assignment yang masih berlaku. Input `operational_unit_id`/`organization_id` ditolak; pilihan `unit_id` diselesaikan ulang melalui query unit berizin. Route binding juga scoped. Aksi mutasi memeriksa izin lagi di dalam transaksi.

`SUPER_ADMIN` sistem memiliki akses dalam organization sendiri. Role operasional yang dikenal memperoleh akses baca; mutasi memerlukan permission eksplisit, bukan sekadar nama role. Kode permission menggunakan nama modul dengan underscore:

- Umum: `<module>.view`, `.create`, `.update`, `.delete` sesuai aksi yang tersedia.
- Penjualan: `sales_orders.checkout` (atau `pos.checkout`). Alias `pos.view`, `pos.create`, `pos.update` juga didukung untuk penjualan.
- Sesi: `register_sessions.create`, `register_sessions.close`, dan `.view`.
- Approval: `sales_refunds.approve`, `sales_refunds.reject`, `stock_counts.approve`, `waste_records.approve`, `daily_closings.approve`, `inventory_reconciliations.approve`.
- Transfer: `stock_transfers.dispatch`, `stock_transfers.receive`.
- Settlement: `payment_settlements.settle`.
- Cancel/void/release/activate/deactivate memakai `.update` pada modul bersangkutan.
- Customer dan batch merupakan data bersama organization; mutasi memerlukan assignment organization-wide.

Tidak ada pemberian permission baru otomatis. Administrator dapat memakai sistem pengelolaan permission yang sudah ada untuk memberikan akses tersebut.

Transaksi diserialisasi dengan lock organization lalu lock dokumen/saldo. Status final tidak dapat diedit biasa; audit tersimpan dalam transaksi yang sama. Penjualan/transfer/pembayaran dapat diulang hanya saat statusnya mengizinkan sehingga checkout/approval berulang tidak memposting ulang.

## Batas implementasi

- Pembayaran dan settlement dicatat manual; tidak terhubung payment gateway dan tidak mengirim dana.
- POS memakai satu metode pembayaran per order. Diskon, pajak, service charge, bundle/modifier, konsumsi BOM, dan retur stok otomatis tidak ditambahkan.
- Harga menggunakan mata uang organization; waktu bisnis mengikuti timezone organization.
- Test menggunakan SQLite in-memory. Lock/concurrency pada MySQL 8 belum diuji dengan proses paralel/database produksi.

## File utama

`TradeController`, `TradeRequest`, `TradePolicy`, `TradeDefinition`, `TradeAccess`, `TradeMutation`, `StockLedger`, `PointOfSale`, Blade `resources/views/trade`, dan `TradeManagementTest`. Integrasi terbatas pada routes, provider binding/gates, serta navigasi.
