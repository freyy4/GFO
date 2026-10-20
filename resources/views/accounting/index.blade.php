@extends('app')
@section('title', $label)
@section('page-heading', $label)
@section('content')
<div class="space-y-6">@include('accounting._navigation')
<div class="flex justify-end">@can('accounting-create',$module)<a class="brand-primary" href="{{ route($module.'.create') }}">Tambah {{ $label }}</a>@endcan</div>
@include('accounting._filters')
@if($module === 'break-even')<p class="brand-help">Estimasi dari snapshot Booth P&L. Nilai kosong berarti margin/pendapatan belum memadai untuk menghitung break-even.</p>@endif
@if($module === 'payment-reconciliations')<p class="brand-help">Daftar settlement dan selisihnya dari proses penjualan. @can('trade-view','payment-settlements')<a class="brand-link" href="{{ route('payment-settlements.index') }}">Buka proses settlement</a>@endcan</p>@endif
<section class="overflow-hidden rounded-xl border border-stone-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar {{ $label }}</caption><thead class="bg-stone-50"><tr>@foreach(['Dokumen / nama','Keterangan','Nilai','Status','Aksi'] as $text)<th class="px-4 py-3" scope="col">{{ $text }}</th>@endforeach</tr></thead><tbody class="divide-y divide-stone-100">
@forelse($records as $record)<tr><th scope="row" class="px-4 py-4">{{ $record->number ?? $record->bill_no ?? $record->name ?? $record->title ?? '#'.$record->id }}</th><td class="px-4 py-4">{{ $options['employee_id'][$record->employee_id ?? $record->targetable_id ?? $record->measurable_id] ?? $options['unit_id'][$record->operational_unit_id] ?? '—' }}</td><td class="px-4 py-4">{{ $module === 'break-even' ? ($record->break_even_sales ?? 'Belum dapat dihitung') : ($record->amount ?? $record->net_pay ?? $record->net_total ?? $record->total_score ?? $record->score ?? $record->allocated_amount ?? $record->variance_amount ?? $record->contribution ?? '—') }}</td><td class="px-4 py-4">@include('accounting._status')</td><td class="px-4 py-4"><a class="brand-link" href="{{ route($module.'.show',$record) }}">Detail</a></td></tr>
@empty<tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold">Belum ada data</p><p class="brand-help">Tambah data atau sesuaikan filter.</p></td></tr>@endforelse
</tbody></table></div><div class="border-t p-4">{{ $records->links() }}</div></section></div>
@endsection
