<section class="brand-panel space-y-5" data-purchasing-lines><div><h2 class="text-lg font-semibold">Detail barang</h2><p class="brand-help">Gunakan satuan dasar item yang tertera pada pilihan. Satuan, snapshot, dan total dihitung server. Maksimal 100 baris; satu item/baris PO hanya satu kali.</p>@if($module === 'goods-receipts')<p class="brand-help">Jumlah diterima tidak boleh melebihi sisa PO. Sebelum konfirmasi, seluruh kuantitas harus diklasifikasikan lolos atau ditolak QC; penolakan memerlukan alasan.</p>@endif</div>
@php($oldLines = old('lines', $lines ?: [[]]))
@php($oldLines = is_array($oldLines) ? $oldLines : [[]])
<div class="space-y-4" data-line-list>@foreach($oldLines as $index => $values)@include('purchasing._line', ['values' => is_array($values) ? $values : []])@endforeach</div>
<template data-line-template>@include('purchasing._line', ['index' => '__INDEX__', 'values' => []])</template>
<button type="button" class="brand-link" data-add-line>Tambah baris</button><p class="text-sm text-stone-500" role="status" data-line-feedback></p>
</section>
<script>
(() => {
const section = document.querySelector('[data-purchasing-lines]');
if (!section) return;
const list = section.querySelector('[data-line-list]');
let next = Math.max(0, ...Array.from(list.querySelectorAll('[name]')).map(el => Number((el.name.match(/lines\[(\d+)\]/) || [0, 0])[1]))) + 1;
const feedback = section.querySelector('[data-line-feedback]');
section.querySelector('[data-add-line]').addEventListener('click', () => {
    if (list.children.length >= 100) { feedback.textContent = 'Maksimal 100 baris.'; return; }
    const fragment = section.querySelector('template').content.cloneNode(true);
    fragment.querySelectorAll('*').forEach(el => ['name', 'id', 'for', 'aria-describedby'].forEach(attr => {
        if (el.hasAttribute(attr)) el.setAttribute(attr, el.getAttribute(attr).replaceAll('__INDEX__', String(next)));
    }));
    next++; list.append(fragment); list.lastElementChild.querySelector('select, input')?.focus(); feedback.textContent = 'Baris baru ditambahkan.';
});
section.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-line]'); if (!button) return;
    if (list.children.length <= 1) { feedback.textContent = 'Dokumen memerlukan minimal satu baris.'; return; }
    if (!window.confirm('Hapus baris ini dari form?')) return;
    button.closest('[data-purchasing-line]').remove(); feedback.textContent = 'Baris dihapus.';
});
const load = document.getElementById('load-po-lines');
load?.addEventListener('click', () => {
    const po = document.getElementById('purchase_order_id').value;
    if (!po) { feedback.textContent = 'Pilih PO terlebih dahulu.'; return; }
    if (!window.confirm('Muat baris PO? Isi form yang belum disimpan akan diganti.')) return;
    const url = new URL(load.dataset.url, window.location.origin); url.searchParams.set('purchase_order_id', po); window.location.assign(url);
});
})();
</script>
