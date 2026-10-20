@extends('app')
@section('title', $label)
@section('page-heading', $label)
@section('content')
<div class="space-y-6">@include('trade._navigation')
@if($summary)<section class="grid gap-4 sm:grid-cols-2"><div class="brand-panel"><p class="brand-help">Item dalam saldo</p><p class="text-2xl font-semibold">{{ $summary['items'] }}</p></div><div class="brand-panel"><p class="brand-help">Lokasi dalam hasil filter</p><p class="text-2xl font-semibold">{{ $summary['units'] }}</p></div></section>@endif
<div class="flex justify-end">@can('trade-create', $module)<a class="brand-primary" href="{{ route($module.'.create') }}">Tambah {{ $label }}</a>@endcan</div>
<form class="brand-panel grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3" method="GET" action="{{ route($module.'.index') }}">
<div><label class="brand-label" for="search">Cari nomor / nama / referensi</label><input class="brand-input" id="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100"></div>
@if($module !== 'customers')<div><label class="brand-label" for="unit_id">Unit operasional</label><select class="brand-input" id="unit_id" name="unit_id"><option value="">Semua unit yang diizinkan</option>@foreach($options['unit_id'] as $id => $name)<option value="{{ $id }}" @selected((string)($filters['unit_id'] ?? '') === (string)$id)>{{ $name }}</option>@endforeach</select></div>@endif
@if(!in_array($module,['inventory','inventory-balances','inventory-movements','customers']))<div><label class="brand-label" for="status">Status</label><select class="brand-input" name="status" id="status"><option value="">Semua status</option>@foreach(['DRAFT','ACTIVE','INACTIVE','OPEN','CLOSED','RELEASED','QUARANTINED','DISPATCHED','RECEIVED','REPORTED','PENDING','APPROVED','COMPLETED','PAID','SETTLED','PARTIALLY_REFUNDED','REFUNDED','REJECTED','CANCELLED','VOID'] as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>@endif
@foreach(['from'=>'Dibuat mulai','to'=>'Dibuat sampai'] as $key => $caption)<div><label for="{{ $key }}" class="brand-label">{{ $caption }}</label><input class="brand-input" type="date" id="{{ $key }}" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}"></div>@endforeach
<div class="flex items-center gap-4"><button class="brand-primary" type="submit">Terapkan</button><a class="brand-link" href="{{ route($module.'.index') }}">Reset</a></div>
</form>
<section class="overflow-hidden rounded-xl border border-stone-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar {{ $label }}</caption><thead class="bg-stone-50"><tr>@foreach(['Dokumen / Item','Unit','Status','Jumlah / Nilai','Aksi'] as $caption)<th class="px-4 py-3" scope="col">{{ $caption }}</th>@endforeach</tr></thead><tbody class="divide-y divide-stone-100">
@forelse($records as $record)<tr><td class="px-4 py-4">{{ $record->number ?? $record->name ?? $record->batch_no ?? ($options['inventory_item_id'][$record->inventory_item_id] ?? '#'.$record->id) }}</td><td class="px-4 py-4">{{ $options['unit_id'][$record->operational_unit_id ?? $record->source_unit_id] ?? '—' }}</td><td class="px-4 py-4">@include('trade._status')</td><td class="px-4 py-4">{{ $record->on_hand ?? $record->quantity ?? $record->total_amount ?? $record->amount ?? $record->net_sales ?? $record->settled_amount ?? '—' }}</td><td class="px-4 py-4"><a class="brand-link" href="{{ route($module.'.show', $record) }}">Detail</a></td></tr>
@empty<tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold">Belum ada data</p><p class="brand-help">Tambahkan data atau sesuaikan filter pencarian.</p></td></tr>@endforelse
</tbody></table></div><div class="border-t p-4">{{ $records->links() }}</div></section>
</div>
@endsection
