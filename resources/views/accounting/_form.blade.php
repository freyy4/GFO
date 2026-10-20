@csrf
<section class="brand-panel space-y-5"><h2 class="text-lg font-semibold">Informasi {{ $label }}</h2><p class="brand-help">Kolom * wajib diisi. Unit dan referensi diperiksa sesuai akses akun.</p>
@if($module === 'payroll-runs')<p role="status" class="brand-help">Anda dapat membuat draft proses payroll. Perhitungan dan pembayaran menunggu formula perusahaan yang disepakati; belum ada gaji otomatis yang dihasilkan.</p>@endif
@if($module === 'kpi-definitions')<p class="brand-help">Realisasi dicatat manual dengan bukti. Skor = aktual ÷ target × 100 (arah lebih tinggi), atau target ÷ aktual × 100 (arah lebih rendah). Skor maksimal 100; aktual nol pada arah lebih rendah menghasilkan 100.</p>@endif
@if($module === 'performance-scores')<p class="brand-help">Skor dihitung dari rata-rata berbobot semua hasil KPI yang disetujui pada periode yang sama. Rating huruf tidak ditetapkan otomatis.</p>@endif
@if($module === 'employee-recognitions')<p class="brand-help">Penghargaan dicatat langsung dan tidak otomatis menambah payroll atau melakukan pembayaran.</p>@endif
@if($module === 'petty-cash')<p class="brand-help">Form ini mencatat dana masuk. Dana keluar dicatat melalui pembayaran expense/tagihan dengan metode Petty Cash.</p><input type="hidden" name="submission_token" value="{{ is_string(old('submission_token')) ? old('submission_token') : (string) \Illuminate\Support\Str::uuid() }}">@endif
@if($module === 'booth-pl')<p class="brand-help">Pilih satu hari lampau atau satu bulan lengkap yang sudah berakhir. Snapshot menggunakan transaksi tercatat dan dapat dihitung ulang. Pendapatan tidak termasuk pajak. Break-even adalah estimasi, bukan jaminan hasil.</p>@endif
<div class="grid gap-5 sm:grid-cols-2">@foreach($fields as $key=>$definition)@include('accounting._field',['name'=>$key,'id'=>$key,'errorKey'=>$key,'default'=>$record->getAttribute($key)])@endforeach</div>
</section>
<div class="flex gap-4"><button type="submit" class="brand-primary">Simpan {{ $label }}</button><a class="brand-link" href="{{ route($module.'.index') }}">Batal</a></div>
