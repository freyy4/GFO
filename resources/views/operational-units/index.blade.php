@extends('operational-units.layout')
@section('title', 'Operational Unit / Booth')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-2xl font-semibold">Operational Unit / Booth</h1><p class="brand-help">Kelola lokasi dan informasi unit operasional Anda.</p></div>@can('create', App\Models\OperationalUnit::class)<a href="{{ route('operational-units.create') }}" class="brand-primary">Tambah unit</a>@endcan</div>
<form method="GET" action="{{ route('operational-units.index') }}" class="brand-panel grid gap-4 sm:grid-cols-2 lg:grid-cols-4" role="search">
    <div><label for="search" class="brand-label">Cari unit</label><input id="search" name="search" type="search" class="brand-input" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Kode, nama, atau kota"></div>
    <div><label for="type" class="brand-label">Tipe unit</label><select id="type" name="type" class="brand-input"><option value="">Semua tipe</option>@foreach(['BOOTH' => 'Booth', 'CENTRAL_KITCHEN' => 'Central Kitchen', 'WAREHOUSE' => 'Warehouse'] as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="status" class="brand-label">Status</label><select id="status" name="status" class="brand-input"><option value="">Semua status</option>@foreach(['ACTIVE' => 'Aktif', 'INACTIVE' => 'Nonaktif'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
    <div class="flex items-end gap-4"><button type="submit" class="brand-primary">Terapkan</button><a href="{{ route('operational-units.index') }}" class="brand-link">Reset</a></div>
</form>
<section class="brand-panel">
    <p class="mb-4 text-sm text-stone-600" role="status">{{ $units->total() }} unit ditemukan</p>
    @if($units->isEmpty())
        <div class="py-12 text-center"><h2 class="text-lg font-semibold">Tidak ada unit yang cocok</h2><p class="brand-help">Ubah pencarian atau filter, atau tambahkan unit baru jika Anda memiliki akses.</p><a class="brand-link" href="{{ route('operational-units.index') }}">Reset pencarian</a></div>
    @else
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Daftar unit operasional"><table class="w-full text-left text-sm"><caption class="sr-only">Unit operasional yang dapat Anda akses</caption><thead><tr class="border-b border-stone-200">@foreach(['Unit', 'Tipe', 'Kota', 'Status', 'Aksi'] as $label)<th scope="col" class="px-3 py-3">{{ $label }}</th>@endforeach</tr></thead><tbody>
        @foreach($units as $unit)<tr class="border-b border-stone-100"><th scope="row" class="px-3 py-4"><a class="brand-link" href="{{ route('operational-units.show', $unit) }}">{{ $unit->name }}</a><p class="brand-help">{{ $unit->code }}</p></th><td class="px-3 py-4">{{ str_replace('_', ' ', $unit->type) }}</td><td class="px-3 py-4">{{ $unit->city ?: '—' }}</td><td class="px-3 py-4">@include('operational-units._status')</td><td class="px-3 py-4"><div class="flex flex-wrap gap-3">@include('operational-units._actions')</div></td></tr>@endforeach
        </tbody></table></div>
        <div class="mt-5">{{ $units->links() }}</div>
    @endif
</section>
@endsection
