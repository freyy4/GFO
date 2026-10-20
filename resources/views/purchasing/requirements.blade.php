@extends('app')
@section('title', $label)
@section('page-heading', $label)
@section('page-description', 'Perhitungan kebutuhan pembelian per unit dari data stok yang tersedia.')
@section('content')
<div class="space-y-6">@include('purchasing._navigation') @include('purchasing._filters')
<p class="rounded-lg bg-blue-50 p-4 text-sm text-blue-950">Tersedia = maksimum(0, on hand − reserved − quarantined). Target = maksimum(reorder point, safety stock). Rekomendasi = maksimum(0, target − tersedia − sisa PO APPROVED/PARTIALLY_RECEIVED). Semua kuantitas menggunakan satuan dasar. Periksa kebutuhan aktual sebelum mengajukan; ini bukan forecast otomatis.</p>
@if(!$unit)<section class="brand-panel text-center"><h2 class="font-semibold">Pilih unit tujuan</h2><p class="brand-help">Kebutuhan dihitung terpisah untuk setiap unit dalam akses Anda.</p></section>
@else<section class="overflow-hidden rounded-xl border border-stone-200 bg-white"><h2 class="p-5 font-semibold">Kebutuhan {{ $unit->name }}</h2><div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Kebutuhan pembelian per item</caption><thead class="bg-stone-50"><tr>@foreach(['Item', 'Satuan', 'Tersedia', 'Sisa PO', 'Reorder point', 'Safety stock', 'Rekomendasi', 'Aksi'] as $caption)<th scope="col" class="whitespace-nowrap px-4 py-3">{{ $caption }}</th>@endforeach</tr></thead><tbody class="divide-y divide-stone-100">
@forelse($items as $item)<tr><td class="px-4 py-4">{{ $item->sku }} · {{ $item->name }}</td><td class="px-4 py-4">{{ $item->baseUom?->code }}</td><td class="px-4 py-4">{{ \App\Support\PurchasingDecimal::max('0', (string)$item->available_snapshot) }}</td><td class="px-4 py-4">{{ $item->on_order }}</td><td class="px-4 py-4">{{ $item->reorder_point }}</td><td class="px-4 py-4">{{ $item->safety_stock }}</td><td class="px-4 py-4 font-semibold">{{ $item->suggested_quantity }}</td><td class="px-4 py-4">@can('purchasing-create', 'purchase-requisitions')<a class="brand-link" href="{{ route('purchase-requisitions.create', ['destination_unit_id' => $unit->id, 'inventory_item_id' => $item->id]) }}">Buat requisition</a>@endcan</td></tr>
@empty<tr><td colspan="8" class="px-6 py-14 text-center">Belum ada item aktif untuk unit dan filter ini.</td></tr>@endforelse
</tbody></table></div><div class="border-t border-stone-100 p-4">{{ $items->links() }}</div></section>@endif
</div>
@endsection
