# Payroll & Performance dan Keuangan

## Status implementasi

Keuangan dan Performance sudah diimplementasikan menggunakan migration/model yang ada. Payroll baru mencakup periode, draft proses, pembatalan draft, dan tampilan slip/alokasi yang sudah tersedia. **Perhitungan, persetujuan, dan pembayaran payroll belum aktif:** pengguna memilih memberikan formula perusahaan, tetapi formula belum diterima. Tidak ada asumsi gaji pokok + penyesuaian manual yang dijalankan menggantikan pilihan tersebut.

Rincian yang diperlukan untuk menyelesaikan payroll: periode/prorata gaji, tunjangan, lembur, keterlambatan/absensi, potongan, pajak/BPJS, pembulatan, dan contoh perhitungan. Endpoint kalkulasi/payroll menolak proses dengan pesan validasi sampai formula diterapkan. Tidak ada slip atau pembayaran otomatis dari draft.

Project menggunakan Laravel 13 sesuai `composer.json`; versi framework dipertahankan. Tidak membuat migration/model atau memasang framework UI baru. Model payroll_policies/payroll_components yang ada belum dipakai sebagai mesin formula sebelum spesifikasi perusahaan tersedia.

## Fungsi

- Periode payroll: daftar, pencarian, detail, create/edit/delete sebelum dipakai; periode tumpang tindih ditolak. Run: satu run aktif per periode, nomor run server, pembatalan draft. Slip/alokasi: baca data existing sesuai scope. Penguncian periode memerlukan run approved/paid dan tidak ada run draft/calculated.
- KPI: definisi manual, arah HIGHER_BETTER/LOWER_BETTER, bobot positif; target karyawan per periode tidak tumpang tindih. Realisasi dengan bukti; skor rasio target dibatasi 0–100. Hasil lebih rendah bernilai aktual nol mendapat skor 100. Review oleh pengguna selain pencatat. Definisi/target yang dipakai dikunci.
- Performance: rata-rata berbobot seluruh hasil KPI yang approved pada periode tepat sama, snapshot bobot dan skor, persetujuan oleh reviewer lain. Tidak menetapkan rating huruf tanpa matriks perusahaan. Target baru ditolak jika periode performance sudah approved.
- Reward/recognition: pencatatan immutable dengan pelaku/waktu server. Tidak otomatis menjadi pembayaran atau komponen payroll.
- Expense: kategori, draft/edit/delete, submit, persetujuan/penolakan oleh pengguna lain, pembayaran dan pembatalan sebelum dibayar. Input amount memakai desimal, bukan kalkulasi floating point.
- Tagihan: create/edit/delete sebelum dipakai, pembayaran sekali, satu expense terkait dibuat secara atomik. Tidak mengirim transfer bank; hanya mencatat pembayaran yang sudah dilakukan beserta referensi.
- Kewajiban berulang: kontrak bulanan, activate/deactivate, generate satu tagihan per bulan dalam masa kontrak; duplikasi/overlap periode ditolak. Periode parsial dapat dibuat sebagai tagihan manual.
- Petty cash: dana masuk dengan token anti-submit ulang; dana keluar hanya melalui pembayaran expense/tagihan. Saldo dihitung ulang dari ledger dengan lock organisasi. Saldo negatif dan pembayaran ulang ditolak. Ledger tidak diedit/dihapus.
- Booth P&L: snapshot harian/bulanan untuk periode selesai dan recalculation. Pendapatan mengurangi pajak dan refund proporsional tanpa pajak berdasarkan tanggal refund. HPP tetap karena alur refund existing tidak mengembalikan persediaan. Expense APPROVED/PAID dihitung satu kali berdasarkan expense_date. Tagihan yang belum dibayar belum menjadi expense dalam alur ini. Expense hasil pembayaran tagihan memakai tanggal pencatatan pembayaran.
- Biaya tenaga kerja P&L: dari alokasi payroll APPROVED/PAID existing, diprorata menurut hari kalender ketika periode laporan hanya mengambil sebagian periode payroll. Pembulatan dua desimal dapat menyebabkan selisih kecil bila laporan harian dijumlahkan dibanding snapshot bulanan.
- Break-even: biaya tetap kategori + tenaga kerja (diasumsikan tetap), dibagi rasio margin setelah HPP dan biaya variabel. Kosong bila pendapatan/margin tidak positif. Ini estimasi operasional dari bauran transaksi, bukan buku besar/jurnal akuntansi atau kepastian omzet impas.
- Rekonsiliasi pembayaran: daftar/detail settlement dan variance existing. Proses settlement tetap menggunakan modul penjualan yang sudah tersedia dengan gate-nya sendiri; tidak menduplikasi posting. Biaya settlement harus dicatat sebagai expense agar masuk P&L, tidak otomatis dijurnal ulang.
- Ringkasan: expense disetujui/dibayar per periode, seluruh tagihan belum dibayar, tunggakan saat ini, saldo petty cash saat ini, variance settlement per periode; terbatas unit berizin. Bukan saldo rekening bank.

## Keamanan dan UI

Setiap action memakai policy dan scope organisasi/unit. Periode/run payroll bersifat organisasi: akun scope satu unit tidak dapat melihat agregat gaji organisasi. Slip berdasarkan unit utama karyawan; alokasi berdasarkan unit alokasi dan organisasi payroll. Role SUPER_ADMIN sistem tetap terbatas organisasi sendiri.

Role HR dapat membaca Payroll/Performance; FINANCE dapat membaca slip/alokasi. DIRECTOR/FINANCE/AUDITOR membaca keuangan sesuai unit role assignment. Mutasi memerlukan permission eksplisit `<module_underscore>.<action>` atau SUPER_ADMIN sistem. Contoh: `expenses.create`, `expenses.submit`, `expenses.approve`, `expenses.pay`, `petty_cash.create`, `petty_cash.pay`, `recurring_obligations.generate`, `booth_pl.recalculate`. Cancel/activate/deactivate memakai update. Master kategori/KPI memerlukan izin organisasi untuk mutasi. Tidak menambah permission ke akun secara otomatis.

Organization, pelaku, nomor transaksi, status, skor, dan saldo ditetapkan server. `unit_id` adalah pilihan yang diverifikasi ulang terhadap scope view dan scope tindakan; `operational_unit_id` dan `organization_id` dari input ditolak. Input menggunakan validated(), CSRF, verb HTTP sesuai aksi, dan Blade escaping. Mutasi dan audit satu transaksi, dengan lock organisasi dan baca ulang state/permission. Kegagalan audit membatalkan perubahan. Tidak ada migrasi dijalankan pada database aplikasi.

UI memakai layout/komponen project termasuk tema dan animasi: index/create/detail/edit sesuai jenis/state, partial form, search/filter tanggal/status/unit, pagination 15 baris, badge, empty state, validation, feedback, loading, konfirmasi, label dan tabel aksesibel. Dokumen immutable dan data turunan tidak menyediakan edit aktif.

## Route

Semua route memakai middleware auth. Prefix:

- Payroll/Performance: `payroll-periods`, `payroll-runs`, `payslips`, `payroll-allocations`, `kpi-definitions`, `kpi-targets`, `kpi-results`, `performance-scores`, `employee-recognitions`.
- Keuangan: `finance`, `expense-categories`, `expenses`, `bills`, `recurring-obligations`, `petty-cash`, `booth-pl`, `break-even`, `payment-reconciliations`.

Resource: GET index/create/show/edit; POST store; PUT update; DELETE destroy. Record menggunakan scoped `{accounting_record}` binding. `finance` hanya index; payslips/payroll-allocations/break-even/payment-reconciliations hanya index/show. Resource immutable lainnya memiliki policy yang menolak edit/delete.

Action PATCH: periode lock; run calculate/approve/pay/cancel (calculate/approve/pay menunggu formula); KPI activate/deactivate; hasil KPI/performance approve; expense submit/approve/reject/pay/cancel; bill pay/cancel; recurring generate/activate/deactivate; P&L recalculate.

## File

Dibuat:
- app/Http/Controllers/AccountingController.php
- app/Http/Requests/AccountingRequest.php
- app/Policies/AccountingPolicy.php
- app/Support/AccountingAccess.php
- app/Support/AccountingDefinition.php
- app/Support/AccountingDisplay.php
- app/Support/AccountingMutation.php
- app/Support/FinanceMutation.php
- app/Support/FinanceReporting.php
- app/Support/PayrollPerformanceMutation.php
- resources/views/accounting/{index,create,edit,show,summary,_navigation,_form,_field,_filters,_status,_calculation}.blade.php
- tests/Feature/AccountingManagementTest.php
- docs/payroll-performance-keuangan.md

Integrasi diubah: routes/web.php, app/Providers/AppServiceProvider.php, app/Support/Navigation.php, config/navigation.php. public/build diperbarui oleh build Vite.

## Pemeriksaan

Feature test memakai SQLite in-memory. Sandbox membatasi penulisan cache view/log milik pengguna lain; test dijalankan dengan `VIEW_COMPILED_PATH=/tmp/gfo-accounting-views LOG_CHANNEL=null`, tanpa mengganti konfigurasi aplikasi. Pengujian MySQL paralel/concurrency belum dilakukan.

Build Vite berhasil memakai Node dari VS Code Server. Peringatan fontaine opsional tidak menghalangi build dan tidak ada dependensi tambahan dipasang.

Verifikasi terakhir: **85 test / 1.058 assertion lulus** (AccountingManagementTest, NavigationLayoutTest, TradeManagementTest, OperationsManagementTest, DashboardTest). `php artisan route:list` berhasil: 120 route untuk dua domain ini, 586 route total.
