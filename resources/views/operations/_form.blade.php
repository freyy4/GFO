@csrf
<section class="brand-panel space-y-5"><h2 class="text-lg font-semibold">Informasi {{ $label }}</h2><p class="brand-help">Kolom * wajib diisi. Waktu menggunakan {{ $timezone }}. Unit dan referensi diperiksa sesuai akses akun.</p>
@if($module === 'demand-forecasts')<p class="brand-help">Baseline diisi manual. Rekomendasi = baseline × (1 + safety%); dibulatkan ke atas sesuai ketelitian item. Override memerlukan alasan.</p>@endif
@if($module === 'production-plans')<p class="brand-help">Target menggunakan satuan hasil recipe. BOM dan sub-recipe dihitung server. Versi recipe harus approved dan berlaku pada tanggal produksi.</p>@endif
@if($module === 'production-batches')<p class="brand-help">Bahan dipakai saat batch dimulai. Hasil masuk karantina dan baru tersedia setelah QC FINAL lulus.</p>@endif
@if($module === 'packing-dispatch')<p class="brand-help">Tugas menandai kesiapan packing/dispatch. Perpindahan stok fisik dilakukan melalui Transfer Stok.</p>@endif
@if($module === 'attendance-records')<p class="brand-help">Catat jam masuk dan keluar aktual untuk ditinjau. Durasi dikurangi waktu istirahat template. Tidak ada verifikasi lokasi otomatis.</p>@endif
@if($module === 'replacement-candidates')<p class="brand-help">Kandidat dipilih manual. Kesesuaian unit, jabatan, jadwal, dan izin diperiksa server.</p>@endif
@if($module === 'shift-assignments')<p class="brand-help">Jam mulai dan selesai ditentukan di sini; template menyediakan aturan istirahat. Kosongkan karyawan untuk slot pengganti.</p>@endif
<div class="grid gap-5 sm:grid-cols-2">@foreach($fields as $key => $definition)
@include('operations._field', ['name'=>$key, 'id'=>$key, 'errorKey'=>$key, 'default'=>$record->getAttribute($key)])
@endforeach</div></section>
@if($lineFields)
<section class="brand-panel space-y-4" data-production-lines><h2 class="font-semibold">Detail perencanaan</h2><p class="brand-help">Gunakan satuan pada recipe/item. Maksimal 100 baris. Hindari baris yang sama.</p>
@php($oldLines = old('lines', $lines ?: [[]]))
<div class="space-y-4" data-line-list>@foreach(is_array($oldLines) ? $oldLines : [[]] as $index => $values)@include('operations._line', ['values' => is_array($values) ? $values : []])@endforeach</div>
<template data-line-template>@include('operations._line', ['index' => '__INDEX__', 'values' => []])</template>
<button class="brand-link" type="button" data-add-line>Tambah baris</button><p role="status" class="brand-help" data-line-feedback></p>
</section>
<script>
(() => {
const section=document.querySelector('[data-production-lines]'); const list=section.querySelector('[data-line-list]'); const feedback=section.querySelector('[data-line-feedback]');
let next=Math.max(0,...Array.from(list.querySelectorAll('[name]')).map(e=>Number((e.name.match(/lines\[(\d+)\]/)||[0,0])[1])))+1;
section.querySelector('[data-add-line]').addEventListener('click',()=>{
 if(list.children.length>=100){feedback.textContent='Maksimal 100 baris.';return;}
 const fragment=section.querySelector('template').content.cloneNode(true);
 fragment.querySelectorAll('*').forEach(el=>['name','id','for','aria-describedby'].forEach(attr=>{if(el.hasAttribute(attr))el.setAttribute(attr,el.getAttribute(attr).replaceAll('__INDEX__',String(next)));}));
 next++;list.append(fragment);list.lastElementChild.querySelector('select,input')?.focus();feedback.textContent='Baris ditambahkan.';
});
section.addEventListener('click',event=>{const button=event.target.closest('[data-remove-line]');if(!button)return;
 if(list.children.length<=1){feedback.textContent='Minimal satu baris diperlukan.';return;}
 if(window.confirm('Hapus baris ini?')){button.closest('[data-production-line]').remove();feedback.textContent='Baris dihapus.';}
});
})();
</script>
@endif
<div class="flex gap-4"><button type="submit" class="brand-primary">Simpan {{ $label }}</button><a class="brand-link" href="{{ route($module.'.index') }}">Batal</a></div>
