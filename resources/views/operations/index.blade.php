@extends('app')
@section('title', $label)
@section('page-heading', $label)
@section('content')
<div class="space-y-6">@include('operations._navigation')
<div class="flex justify-end">@can('operations-create',$module)<a class="brand-primary" href="{{ route($module.'.create') }}">Tambah {{ $label }}</a>@endcan</div>
<form class="brand-panel grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3" method="GET" action="{{ route($module.'.index') }}" role="search">
<div><label for="search" class="brand-label">Cari nama / nomor / ID</label><input class="brand-input" type="search" name="search" id="search" maxlength="100" value="{{ $filters['search'] ?? '' }}"></div>
<div><label for="unit_id" class="brand-label">Unit</label><select class="brand-input" name="unit_id" id="unit_id"><option value="">Semua unit berizin</option>@foreach($options['unit_id'] as $id=>$text)<option value="{{ $id }}" @selected((string)($filters['unit_id'] ?? '') === (string)$id)>{{ $text }}</option>@endforeach</select></div>
@if(\App\Support\OperationsDefinition::statuses($module))
<div><label for="status" class="brand-label">Status</label><select class="brand-input" name="status" id="status"><option value="">Semua status</option>@foreach(\App\Support\OperationsDefinition::statuses($module) as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
@endif
@foreach(['from'=>'Dibuat mulai','to'=>'Dibuat sampai'] as $key=>$text)<div><label for="{{ $key }}" class="brand-label">{{ $text }}</label><input class="brand-input" type="date" name="{{ $key }}" id="{{ $key }}" value="{{ $filters[$key] ?? '' }}"></div>@endforeach
<div class="flex items-center gap-4"><button class="brand-primary" type="submit">Terapkan</button><a class="brand-link" href="{{ route($module.'.index') }}">Reset</a></div>
</form>
<section class="overflow-hidden rounded-xl border border-stone-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar {{ $label }}</caption><thead class="bg-stone-50"><tr>@foreach(['Dokumen / nama','Keterangan','Status','Aksi'] as $text)<th class="px-4 py-3" scope="col">{{ $text }}</th>@endforeach</tr></thead><tbody class="divide-y divide-stone-100">
@forelse($records as $record)<tr><th scope="row" class="px-4 py-4">{{ $record->number ?? $record->batch_no ?? $record->name ?? '#'.$record->id }}</th><td class="px-4 py-4">{{ $options['employee_id'][$record->employee_id] ?? $options['inventory_item_id'][$record->inventory_item_id] ?? $options['unit_id'][$record->operational_unit_id ?? $record->production_unit_id] ?? ($record->forecast_date?->format('d M Y') ?? '—') }}</td><td class="px-4 py-4">@include('operations._status')</td><td class="px-4 py-4"><a class="brand-link" href="{{ route($module.'.show',$record) }}">Detail</a></td></tr>
@empty<tr><td colspan="4" class="px-6 py-14 text-center"><p class="font-semibold">Belum ada data</p><p class="brand-help">Tambah data atau sesuaikan filter.</p></td></tr>@endforelse
</tbody></table></div><div class="border-t p-4">{{ $records->links() }}</div></section></div>
@endsection
