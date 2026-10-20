@csrf
@if($module === 'inventory-movements')<input type="hidden" name="submission_token" value="{{ is_string(old('submission_token')) ? old('submission_token') : (string) \Illuminate\Support\Str::uuid() }}">@endif
<section class="brand-panel space-y-5"><h2 class="text-lg font-semibold">Informasi {{ $label }}</h2>
<p class="brand-help">Kolom * wajib diisi. Organization, petugas, status, dan nilai perhitungan ditentukan sistem.</p>
@if($module === 'sales-orders')<p class="rounded-lg bg-blue-50 p-3 text-sm">Buka sesi kasir terlebih dahulu. Harga memakai harga unit aktif atau harga dasar varian. Satu kuantitas penjualan memakai satu satuan dasar item stok yang terhubung. Draft belum mengurangi stok; stok dipotong saat checkout. Pajak, diskon, dan service charge tidak ditambahkan pada alur ini.</p>@endif
@if($module === 'inventory-movements')<p class="rounded-lg bg-amber-50 p-3 text-sm">Penyesuaian langsung memposting stok dan ledger. Gunakan hanya untuk saldo awal atau koreksi yang memiliki alasan. Dokumen tidak dapat diedit setelah disimpan.</p>@endif
@if($module === 'inventory-batches')<p class="brand-help">Pembuatan batch belum menambah saldo stok. Gunakan penyesuaian stok dengan alasan setelah batch dibuat.</p>@endif
@if($module === 'sales-refunds')<p class="brand-help">Refund hanya mencatat pembayaran yang dikembalikan. Tidak otomatis mengembalikan stok atau mengirim instruksi ke payment gateway.</p>@endif
@if($module === 'inventory-reconciliations')<p class="brand-help">Ringkasan ledger dan opname yang disetujui pada tanggal bisnis. Saldo awal harus sudah tercatat di ledger. Rekonsiliasi tidak memposting penyesuaian kedua.</p>@endif
<div class="grid gap-5 sm:grid-cols-2">
@foreach($fields as $key => $definition)
@include('trade._field', ['name' => $key, 'id' => $key, 'errorKey' => $key, 'default' => $record->getAttribute($key)])
@endforeach
</div></section>
@if($lineFields)
<section class="brand-panel space-y-4" data-trade-lines><h2 class="font-semibold">Detail barang</h2><p class="brand-help">Gunakan satuan dasar item. Maksimal 100 baris. Kombinasi item/batch tidak boleh diulang.</p>
@php($oldLines = old('lines', $lines ?: [[]]))
<div class="space-y-4" data-line-list>@foreach(is_array($oldLines) ? $oldLines : [[]] as $index => $values)@include('trade._line', ['values' => is_array($values) ? $values : []])@endforeach</div>
<template data-line-template>@include('trade._line', ['index' => '__INDEX__', 'values' => []])</template>
<button class="brand-link" type="button" data-add-line>Tambah barang</button><p role="status" class="brand-help" data-line-feedback></p>
</section>
<script>
(() => {
const section=document.querySelector('[data-trade-lines]'); const list=section.querySelector('[data-line-list]'); const feedback=section.querySelector('[data-line-feedback]');
let next=Math.max(0,...Array.from(list.querySelectorAll('[name]')).map(e=>Number((e.name.match(/lines\[(\d+)\]/)||[0,0])[1])))+1;
section.querySelector('[data-add-line]').addEventListener('click',()=>{
 if(list.children.length>=100){feedback.textContent='Maksimal 100 baris.';return;}
 const fragment=section.querySelector('template').content.cloneNode(true);
 fragment.querySelectorAll('*').forEach(el=>['name','id','for','aria-describedby'].forEach(attr=>{if(el.hasAttribute(attr))el.setAttribute(attr,el.getAttribute(attr).replaceAll('__INDEX__',String(next)));}));
 next++;list.append(fragment);list.lastElementChild.querySelector('select,input')?.focus();feedback.textContent='Baris ditambahkan.';
});
section.addEventListener('click',event=>{const button=event.target.closest('[data-remove-line]');if(!button)return;
 if(list.children.length<=1){feedback.textContent='Minimal satu baris diperlukan.';return;}
 if(window.confirm('Hapus baris ini?')){button.closest('[data-trade-line]').remove();feedback.textContent='Baris dihapus.';}
});
})();
</script>
@endif
<div class="flex gap-4"><button type="submit" class="brand-primary">Simpan {{ $label }}</button><a class="brand-link" href="{{ route($module.'.index') }}">Batal</a></div>
