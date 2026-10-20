@extends('app')
@section('title', 'Manajemen '.$label)
@section('page-heading', 'Manajemen '.$label)
@section('page-description', 'Kelola master data dalam cakupan akses Anda.')
@section('content')
<div class="space-y-6">
<div class="flex flex-wrap items-center justify-between gap-4"><p class="text-sm text-stone-600">{{ $records->total() }} data sesuai filter</p>@can('create', $modelClass)<a class="brand-primary" href="{{ route($module.'.create') }}">Tambah {{ $label }}</a>@endcan</div>
<form method="GET" action="{{ route($module.'.index') }}" class="brand-panel grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3">
<div><label class="brand-label" for="search">Cari {{ $label }}</label><input class="brand-input" name="search" id="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $module === 'employees' ? 'Nomor atau nama karyawan' : 'Kode atau nama' }}"></div>
@foreach($filterFields as $key)
@php([$fieldLabel, $type, $required, $limit] = $fields[$key])
<div><label class="brand-label" for="{{ $key }}">{{ $fieldLabel }}</label>
@if(in_array($type, ['select', 'reference']))
@php($choices = $type === 'select' ? array_combine($limit, $limit) : ($options[$key] ?? []))
<select class="brand-input" id="{{ $key }}" name="{{ $key }}"><option value="">Semua</option>@foreach($choices as $id => $text)<option value="{{ $id }}" @selected((string)($filters[$key] ?? '') === (string)$id)>{{ $text }}</option>@endforeach</select>
@else<input class="brand-input" id="{{ $key }}" name="{{ $key }}" maxlength="40" value="{{ $filters[$key] ?? '' }}" placeholder="Semua area">@endif
</div>
@endforeach
@if($module !== 'positions')<div><label class="brand-label" for="archived">Arsip</label><select class="brand-input" id="archived" name="archived">@foreach(['no' => 'Tidak diarsipkan', 'yes' => 'Diarsipkan', 'all' => 'Semua data'] as $id => $text)<option value="{{ $id }}" @selected(($filters['archived'] ?? 'no') === $id)>{{ $text }}</option>@endforeach</select></div>@endif
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Terapkan</button><a href="{{ route($module.'.index') }}" class="brand-link">Reset filter</a></div>
</form>
<div class="rounded-xl border border-stone-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar {{ $label }}</caption>
<thead class="bg-stone-50"><tr><th scope="col" class="px-4 py-4">{{ $module === 'employees' ? 'Nomor' : 'Kode' }}</th><th scope="col" class="px-4 py-4">Nama</th><th scope="col" class="px-4 py-4">{{ $module === 'positions' ? 'Area kerja' : 'Status' }}</th><th scope="col" class="px-4 py-4">{{ $module === 'employees' ? 'Unit asal / Jabatan' : ($module === 'suppliers' ? 'Kontak / Risiko' : 'Keahlian') }}</th><th scope="col" class="px-4 py-4">Aksi</th></tr></thead>
<tbody class="divide-y divide-stone-100">
@forelse($records as $record)<tr>
<td class="px-4 py-4 font-mono">{{ $record->getAttribute($module === 'employees' ? 'employee_no' : 'code') }}</td><td class="px-4 py-4"><a class="brand-link" href="{{ route($module.'.show', $record) }}">{{ $record->getAttribute($module === 'employees' ? 'full_name' : 'name') }}</a></td>
<td class="px-4 py-4">@if($module === 'positions'){{ $record->work_area ?? '—' }}@else@include('directory._status')@endif</td>
<td class="px-4 py-4">@if($module === 'employees'){{ $options['home_unit_id'][$record->home_unit_id] ?? '—' }} / {{ $options['position_id'][$record->position_id] ?? '—' }}@elseif($module === 'suppliers'){{ $record->contact_name ?? '—' }} / {{ $record->risk_level }}@else{{ $record->required_skills ? 'Tersedia' : 'Belum diisi' }}@endif</td>
<td class="min-w-40 px-4 py-4"><details><summary class="cursor-pointer">Aksi <span class="sr-only">{{ $record->getAttribute($module === 'employees' ? 'full_name' : 'name') }}</span></summary><div class="mt-3">@include('directory._actions')</div></details></td>
</tr>@empty<tr><td colspan="5" class="px-6 py-16 text-center"><p class="text-lg font-semibold">Belum ada data yang dapat ditampilkan</p><p class="mt-2 text-stone-600">Tambah data baru jika Anda memiliki izin, atau ubah pencarian dan filter.</p></td></tr>@endforelse
</tbody></table></div><div class="border-t border-stone-100 p-4">{{ $records->links() }}</div></div>
</div>
@endsection
