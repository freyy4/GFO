@extends('app')
@section('title', 'Detail inventory item')
@section('page-heading', $item->sku.' · '.$item->name)
@section('page-description', 'Identitas bahan dan parameter inventory dalam organization.')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">@include('_catalog-status', ['active' => $item->is_active, 'archived' => $item->trashed()])<div class="flex flex-wrap items-center gap-4">@include('inventory-items._actions')</div></div>
<section class="brand-panel"><h2 class="mb-5 text-lg font-semibold">Informasi item</h2><dl class="grid gap-6 sm:grid-cols-2">
@foreach(['SKU' => $item->sku, 'Nama' => $item->name, 'Brand' => $brand?->name ?? 'Bersama dalam organization', 'Jenis item' => $types[$item->item_type] ?? $item->item_type, 'Satuan dasar' => $item->baseUom?->code, 'Pelacakan' => $item->tracking_policy, 'Pengeluaran' => $item->issue_policy, 'Titik pemesanan ulang' => $item->reorder_point, 'Stok pengaman' => $item->safety_stock, 'Biaya standar' => $item->standard_cost, 'Diperbarui pada' => $item->updated_at?->format('d M Y H:i')] as $label => $value)<div><dt class="brand-label">{{ $label }}</dt><dd class="break-words">{{ $value ?? '—' }}</dd></div>@endforeach
</dl></section>
@if($inUse)<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">Item sudah digunakan. Identitas, satuan dasar, dan kebijakan dilindungi. Item dapat dinonaktifkan melalui edit, tetapi tidak dapat diarsipkan.</div>@endif
<p class="text-sm text-slate-500">Parameter inventory bukan saldo stok aktual. Pemulihan arsip mengembalikan item dalam status nonaktif.</p>
<a href="{{ route('inventory-items.index') }}" class="brand-link">Kembali ke daftar item</a>
@endsection
