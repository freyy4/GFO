@extends('app')
@section('title', $label)
@section('page-heading', $label)
@section('page-description', 'Dokumen dan informasi purchasing dalam cakupan akses Anda.')
@section('content')
<div class="space-y-6">@include('purchasing._navigation')
<div class="flex flex-wrap items-center justify-between gap-4"><p class="text-sm text-stone-500">{{ $records->total() }} data sesuai filter</p>@can('purchasing-create', $module)<a class="brand-primary" href="{{ route($module.'.create') }}">Tambah {{ $label }}</a>@endcan</div>
@include('purchasing._filters')
<p class="text-xs text-stone-500">Tanggal filter mengikuti tanggal dokumen / awal berlaku; zona waktu {{ $timezone }}.</p>
<div class="rounded-xl border border-stone-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar {{ $label }}</caption><thead class="bg-stone-50"><tr>@foreach(['Dokumen / Barang', 'Supplier / Unit', 'Tanggal', 'Nilai / Harga', 'Status', 'Aksi'] as $caption)<th scope="col" class="whitespace-nowrap px-4 py-3">{{ $caption }}</th>@endforeach</tr></thead><tbody class="divide-y divide-stone-100">
@forelse($records as $record)
@php($reference = $record->number ?? $record->contract_no ?? ($module === 'supplier-price-history' ? $record->supplierItem?->inventoryItem?->name : $record->inventoryItem?->name) ?? '#'.$record->id)
@php($date = $record->required_at ?? $record->order_date ?? $record->received_at ?? $record->starts_on ?? $record->effective_from ?? $record->created_at)
<tr><td class="px-4 py-4"><a class="brand-link" href="{{ route($module.'.show', $record) }}">{{ $reference }}</a></td><td class="px-4 py-4">{{ $record->supplier?->name ?? ($module === 'supplier-price-history' ? $record->supplierItem?->supplier?->name : null) ?? '—' }}<span class="block text-xs text-stone-500">{{ $record->destinationUnit?->name }}</span></td><td class="whitespace-nowrap px-4 py-4">{{ $date?->format('d M Y') }}</td><td class="whitespace-nowrap px-4 py-4">{{ $record->currency }} {{ $record->total_amount ?? $record->price ?? '—' }}</td><td class="px-4 py-4">@include('purchasing._status')</td><td class="min-w-44 px-4 py-4"><details><summary class="cursor-pointer">Aksi <span class="sr-only">{{ $reference }}</span></summary><div class="mt-3">@include('purchasing._actions')</div></details></td></tr>
@empty<tr><td colspan="6" class="px-6 py-16 text-center"><h2 class="font-semibold">Belum ada data sesuai filter</h2><p class="mt-2 text-stone-500">Ubah filter atau tambahkan dokumen jika Anda memiliki izin.</p></td></tr>@endforelse
</tbody></table></div><div class="border-t border-stone-100 p-4">{{ $records->links() }}</div></div>
</div>
@endsection
