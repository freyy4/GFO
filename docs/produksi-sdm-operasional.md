# Produksi dan SDM & Operasional

Implementasi memakai model, migration, layout Blade, Tailwind, dan ledger stok yang sudah ada. Tidak menambah migration/model/framework UI. Project saat diperiksa memakai Laravel 13 (`composer.json`), sehingga tidak dilakukan downgrade ke Laravel 12.

## Alur tersedia

- Produksi: forecast manual + safety/override beralasan; rencana dan persetujuan; perhitungan kebutuhan bahan termasuk sub-recipe/waste dan presisi UOM; jadwal, tugas, prasyarat dan penanggung jawab; batch dengan pemakaian aktual bahan FIFO/FEFO; pencatatan hasil karantina; pemeriksaan QC; pelepasan atau penolakan batch; tugas packing/dispatch.
- SDM: template shift termasuk lintas tengah malam; roster draft/publikasi; penugasan; izin/cuti; pencatatan kehadiran manual, persetujuan, koreksi oleh reviewer lain; slot pengganti, kandidat, claim, swap; master skill, verifikasi skill dan sertifikasi karyawan.
- Semua daftar memiliki pagination 15 baris, pencarian ID/nama/nomor (termasuk nama/nomor karyawan pada dokumen terkait), filter tanggal pembuatan, unit, dan status sesuai dokumen. Halaman detail, create/edit untuk data yang dapat diubah, partial form, feedback, konfirmasi, dan loading memakai pola project.
- Dokumen transaksi yang sudah berjalan tidak dapat diedit/dihapus sembarangan. QC dicatat immutable, kehadiran diperbaiki melalui koreksi. Kebutuhan bahan hanya dibaca karena dihasilkan dari rencana.

## Keamanan dan keputusan alur

- Route binding dan policy membatasi organization/unit; unit pilihan diperiksa lagi terhadap izin tindakan. ID organization dan status/pelaku peristiwa ditetapkan server. Input mutasi menggunakan `validated()`; form memakai CSRF dan verb HTTP sesuai tindakan.
- Transaksi mengunci organization dan record, membaca ulang status/akses, lalu menulis dokumen, stok, dan audit bersama. Gagal audit membatalkan perubahan. MySQL concurrency belum diuji secara paralel; feature test memakai SQLite in-memory.
- SUPER_ADMIN sistem mempunyai akses dalam organization sendiri. Akun lain membutuhkan permission `<module_dengan_underscore>.<action>`, misalnya `production_batches.start`, `production_batches.release`, `rosters.publish`, `leave_requests.approve`. Mutasi biasa memakai `create`, `update`, `delete`; cancel/activate/deactivate/revoke memakai `update`. Izin membaca saja tidak mengizinkan perubahan.
- Default akses baca produksi: OPERATIONS_MANAGER/KITCHEN_MANAGER. Default akses baca SDM: HR/OPERATIONS_MANAGER/KITCHEN_MANAGER/BOOTH_SUPERVISOR/CREW. CREW dibatasi ke data pribadinya untuk dokumen karyawan; roster hanya published dan rincian miliknya. Slot terbuka dapat dibaca pada unit berizin. Role assignment harus aktif dan dalam organization yang sah. Tidak menambahkan permission ke akun operasional secara otomatis.
- Data izin/skill/sertifikasi menggunakan unit utama karyawan untuk izin mutasi. Jadwal menggunakan unit shift; penugasan sekunder karyawan harus mencakup periode shift. Benturan shift dan izin diperiksa ulang saat persetujuan claim/swap.
- Persetujuan izin membuat pengganti untuk keseluruhan shift yang terkena izin, termasuk bila izin hanya meliputi sebagian shift. Roster draft tetap harus dipublikasikan sebelum slot penggantinya dapat diisi.
- Kehadiran manual membutuhkan jam masuk dan keluar; durasi dikurangi istirahat template. Tidak mengklaim validasi GPS, tidak menghitung gaji, dan menolak perubahan pada periode payroll LOCKED. Koreksi/izin/kehadiran/claim/swap tidak disetujui sendiri oleh peserta/pemohon.
- Forecast bukan prediksi otomatis. Kandidat bukan ranking otomatis: skor kelayakan tetap dan pemeriksaan unit, jabatan, benturan jadwal, izin; JSON `positions.required_skills` belum memiliki format baku sehingga tidak dipakai sebagai aturan ranking. Skill/sertifikasi dapat dikelola dan diverifikasi terpisah.
- Satu jadwal produksi aktif per unit pada rentang waktu yang sama. Konversi UOM tidak diestimasi: bahan/sub-recipe harus menggunakan satuan yang sesuai. Batch hanya dimulai pada tanggal produksinya; pemakaian bahan tidak dapat dibatalkan setelah dimulai.
- Hasil aktual maksimal target batch. Semua hasil masuk batch inventory karantina. Release memerlukan FINAL PASS, tanpa pemeriksaan FAIL, stok karantina utuh, dan belum kedaluwarsa. Batch gagal tetap dikarantina untuk ditangani melalui proses waste yang sudah ada; tidak ada rework otomatis.
- Packing/dispatch baru dapat dimulai setelah seluruh target baris rencana selesai dan lolos QC. Penyelesaian tugas tidak memindahkan stok; gunakan Transfer Stok yang sudah ada.

## Route

Terdapat 174 route baru untuk 21 prefix berikut. Nama route mengikuti `<prefix>.<action>`; URI prefix sama dengan nama di bawah.

Produksi: `demand-forecasts`, `production-plans`, `production-material-requirements`, `production-schedules`, `production-tasks`, `production-batches`, `production-qc`, `packing-dispatch`.

SDM: `shift-templates`, `rosters`, `shift-assignments`, `leave-requests`, `attendance-records`, `attendance-corrections`, `open-shifts`, `replacement-candidates`, `shift-claims`, `shift-swaps`, `skills`, `employee-skills`, `employee-certifications`.

- `GET /<prefix>` index; `GET /<prefix>/create`; `POST /<prefix>` store; `GET /<prefix>/{operation_record}` show; `GET /<prefix>/{operation_record}/edit`; `PUT /<prefix>/{operation_record}` update; `DELETE /<prefix>/{operation_record}` destroy. Policy menolak edit/delete pada jenis/status immutable.
- Kebutuhan bahan hanya menyediakan index/show.
- `PATCH /<prefix>/{operation_record}/<action>`: forecast approve; plan approve/cancel; schedule cancel; tugas/packing start/finish/cancel; batch start/complete/release/reject/cancel; template activate/deactivate; roster publish; penugasan/open-shift cancel; izin/kehadiran/koreksi/claim/swap approve/reject; skill karyawan verify; sertifikasi revoke.

## File implementasi

Dibuat:
- `app/Http/Controllers/OperationsController.php`
- `app/Http/Requests/OperationsRequest.php`, `ProductionRequest.php`
- `app/Policies/ProductionPolicy.php`, `WorkforcePolicy.php`
- `app/Support/OperationsAccess.php`, `OperationsContext.php`, `OperationsDefinition.php`, `OperationsMutation.php`
- `app/Support/ProductionAccess.php`, `ProductionBom.php`, `ProductionDefinition.php`, `ProductionMutation.php`
- `app/Support/WorkforceAccess.php`, `WorkforceDefinition.php`, `WorkforceMutation.php`
- `resources/views/operations/index.blade.php`, `create.blade.php`, `show.blade.php`, `edit.blade.php`, `_form.blade.php`, `_field.blade.php`, `_line.blade.php`, `_navigation.blade.php`, `_status.blade.php`
- `tests/Feature/OperationsManagementTest.php`
- `docs/produksi-sdm-operasional.md`

Diubah untuk integrasi: `routes/web.php`, `app/Providers/AppServiceProvider.php`, `app/Support/Navigation.php`, `config/navigation.php`. Aset `public/build/` dihasilkan ulang melalui Vite.

## Verifikasi

Perintah: `php artisan test --compact`, `php artisan route:list`, `php artisan optimize:clear`, `npm run build` (Node dari instalasi VS Code Server karena Node sistem lebih lama daripada kebutuhan Vite). Build mengeluarkan peringatan dependensi fontaine opsional; build tetap berhasil tanpa memasang dependensi baru.

Feature test khusus modul: **22 test / 246 assertion lulus**, termasuk alur produksi hingga release, stok kurang/rollback, BOM bertingkat dan siklus, izin/claim/swap, benturan jadwal saat approval, kehadiran/koreksi/payroll lock, roster draft, isolasi organisasi/unit/CREW, penolakan field server, dan rollback ketika audit gagal.

Suite regresi seluruh project: **176 test / 2.147 assertion lulus**. Satu test tambahan roster draft kemudian diverifikasi dalam run khusus 22 test di atas. `route:list` berhasil (466 total route, 174 untuk dua domain ini); build Vite berhasil.
