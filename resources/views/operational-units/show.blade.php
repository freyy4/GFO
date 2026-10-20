@extends('operational-units.layout')
@section('title', $unit->name)
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-2xl font-semibold">{{ $unit->name }}</h1><p class="brand-help">{{ $unit->code }}</p></div><div class="flex flex-wrap items-center gap-4">@include('operational-units._status') @include('operational-units._actions')</div></div>
<section class="brand-panel"><h2 class="mb-5 text-lg font-semibold">Detail unit operasional</h2><dl class="grid gap-5 sm:grid-cols-2">
@foreach(['Organization' => auth()->user()->organization->name, 'Tipe' => str_replace('_', ' ', $unit->type), 'Brand' => $brand?->name, 'Unit induk' => $parent?->name, 'Alamat' => $unit->address, 'Kota' => $unit->city, 'Latitude' => $unit->latitude, 'Longitude' => $unit->longitude, 'Radius geofence (meter)' => $unit->geofence_radius_m, 'Jam buka' => $unit->opens_at?->format('H:i'), 'Jam tutup' => $unit->closes_at?->format('H:i'), 'Zona waktu' => $unit->timezone] as $label => $value)
<div><dt class="brand-label">{{ $label }}</dt><dd class="whitespace-pre-wrap break-words">{{ $value ?? '—' }}</dd></div>
@endforeach
</dl></section>
@if($unit->settings !== null)<section class="brand-panel"><h2 class="mb-4 text-lg font-semibold">Pengaturan tambahan</h2><pre class="whitespace-pre-wrap break-words text-sm">{{ json_encode($unit->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></section>@endif
<a class="brand-link" href="{{ route('operational-units.index') }}">Kembali ke daftar unit</a>
@endsection
