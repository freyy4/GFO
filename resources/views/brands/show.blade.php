@extends('brands.layout')
@section('title', $brand->name)
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><div><p class="mb-2 font-mono text-stone-500">{{ $brand->code }}</p><h1 class="text-3xl font-semibold">{{ $brand->name }}</h1></div>@include('brands._status')</div>
<div class="brand-panel">@include('brands._actions')</div>
<div class="grid gap-6 md:grid-cols-2">
    <section class="brand-panel"><h2 class="mb-5 text-lg font-semibold">Informasi Brand</h2><dl class="space-y-4">
        @foreach(['Organization' => $brand->organization->name, 'Business model' => $brand->business_model, 'Status' => $brand->status, 'Tanggal aktivasi' => $brand->activated_at?->timezone($brand->organization->timezone)->format('d M Y H:i T') ?? 'Belum diaktifkan', 'Jumlah operational unit' => $brand->operational_units_count] as $label => $value)
        <div><dt class="text-sm text-stone-500">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value }}</dd></div>@endforeach
    </dl></section>
    <div class="space-y-6"><section class="brand-panel"><h2 class="mb-3 text-lg font-semibold">Konfigurasi Inventory</h2><p>{{ $brand->inventory_policy }}</p></section><section class="brand-panel"><h2 class="mb-3 text-lg font-semibold">Konfigurasi Costing</h2><p>{{ $brand->costing_method }}</p></section></div>
</div>
<section class="brand-panel"><h2 class="mb-4 text-lg font-semibold">Settings</h2><pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-lg bg-stone-50 p-4 text-sm">{{ $brand->settings !== null ? json_encode($brand->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'Tidak ada pengaturan tambahan.' }}</pre></section>
<a class="brand-link" href="{{ route('brands.index') }}">Kembali ke daftar brand</a>
@endsection
