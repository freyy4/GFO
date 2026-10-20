# GFO

Aplikasi pengelolaan operasional usaha yang mencakup produk dan resep, pengadaan, persediaan, penjualan, produksi, SDM, serta keuangan.

<!-- APP-VERSION:START -->
**Versi pencatatan:** `v0.1.0`  
**Terakhir diperbarui:** 20 Oktober 2026 (WIB)
<!-- APP-VERSION:END -->

## Fitur utama

- Master produk, varian, kategori, brand, satuan, resep, dan versi resep.
- Supplier, pembelian, inventory, dan perdagangan/penjualan.
- Perencanaan produksi, batch, pemeriksaan QC, serta packing dan dispatch.
- Karyawan, jabatan, unit operasional, shift, roster, kehadiran, dan izin.
- Akuntansi, KPI/performance, serta administrasi periode dan draft payroll.

**Batasan payroll:** perhitungan, persetujuan, dan pembayaran payroll belum aktif; menunggu formula perusahaan. Lihat [rincian payroll dan keuangan](docs/payroll-performance-keuangan.md).

Dokumentasi modul: [penjualan dan inventory](docs/penjualan-inventory.md), [produksi dan SDM](docs/produksi-sdm-operasional.md).

## Teknologi dan menjalankan lokal

PHP 8.3+, Laravel v13.33.0, Blade, dan Vite. Gunakan versi Node yang sesuai `package.json` dan paket Vite yang terkunci pada proyek.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
# Atur koneksi database dan layanan yang dibutuhkan di .env terlebih dahulu.
php artisan migrate
npm run build
php artisan serve
```

Langkah di atas untuk instalasi lokal baru. Jangan menimpa `.env` server yang sudah berjalan. Konfigurasi database, integrasi, dan kredensial berada di `.env` dan tidak diunggah ke GitHub. Worker antrean dan scheduler perlu dijalankan terpisah untuk fitur yang menggunakannya.

## Versi dan riwayat update

`v0.1.0` adalah awal pencatatan versi pada README, bukan klaim versi rilis historis atau kesiapan produksi. Setiap commit melalui auto-sync server berikutnya menaikkan patch (`v0.1.1`, `v0.1.2`, dst.) dan menambahkan catatan bertanggal WIB. Ini versi snapshot sinkronisasi; tidak membuat tag atau GitHub Release.

Ringkasan otomatis dibentuk dari file dan jenis perubahan. Ringkasan menjelaskan area yang tersentuh, bukan jaminan fitur selesai atau pengujian lulus. Update manual di luar auto-sync perlu dicatat sendiri. Bagian otomatis dikelola server; catatan tambahan dapat ditulis di luar penandanya.

<!-- AUTO-UPDATES:START -->
| Tanggal (WIB) | Versi | Update |
| --- | --- | --- |
| 20 Oktober 2026 | v0.1.0 | Mulai pencatatan versi; README menjelaskan aplikasi, fitur, cara menjalankan, dan riwayat perubahan. |
<!-- AUTO-UPDATES:END -->

### Riwayat sebelum pencatatan versi

Tanggal mengikuti commit Git yang tersedia; nomor versi lama tidak direka.

| Tanggal | Versi | Perubahan yang tercatat |
| --- | --- | --- |
| 20 Oktober 2026 | Belum diberi versi | Snapshot awal kode pada Git (`fe03ec8`), termasuk master data, perdagangan, inventory, produksi, SDM, dan keuangan. Tanggal pencatatan tidak menunjukkan tanggal pembuatan setiap fitur. |
