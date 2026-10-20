@extends('brands.layout')
@section('title', 'Manajemen Brand')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-3xl font-semibold">Manajemen Brand</h1><p class="mt-2 text-stone-600">Kelola identitas, kebijakan, dan status brand Anda.</p></div>@can('create', App\Models\Brand::class)<a class="brand-primary" href="{{ route('brands.create') }}">Tambah Brand</a>@endcan</div>
<div class="grid gap-4 sm:grid-cols-3">
    @foreach(['all' => 'Semua brand', 'active' => 'Brand aktif', 'draft' => 'Brand draft'] as $key => $label)
    <section class="brand-panel"><p class="text-sm text-stone-600">{{ $label }}</p><p class="mt-2 text-3xl font-semibold">{{ $counts[$key] }}</p>@if($key === 'all')<p class="mt-1 text-xs text-stone-500">Termasuk brand yang diarsipkan.</p>@endif</section>
    @endforeach
</div>
<form method="GET" action="{{ route('brands.index') }}" class="brand-panel grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div><label for="search" class="brand-label">Cari brand</label><input class="brand-input" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Kode atau nama" maxlength="255"></div>
    <div><label for="status" class="brand-label">Status</label><select class="brand-input" id="status" name="status"><option value="">Semua status</option>@foreach(['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'] as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div><label for="business_model" class="brand-label">Business model</label><select class="brand-input" id="business_model" name="business_model"><option value="">Semua business model</option>@foreach($businessModels as $model)<option @selected(($filters['business_model'] ?? '') === $model)>{{ $model }}</option>@endforeach</select></div>
    <div class="flex items-center gap-4"><button type="submit" class="brand-primary">Terapkan</button><a class="brand-link" href="{{ route('brands.index') }}">Reset filter</a></div>
</form>
<div class="rounded-xl border border-stone-200 bg-white">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar brand dalam organization Anda</caption>
        <thead class="border-b border-stone-200 bg-stone-50 text-stone-600"><tr>@foreach(['Kode', 'Nama brand', 'Business model', 'Inventory policy', 'Costing method', 'Status', 'Tanggal aktivasi', 'Aksi'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-4 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-stone-100">@forelse($brands as $brand)<tr>
            <td class="px-4 py-4 font-mono">{{ $brand->code }}</td><td class="px-4 py-4 font-medium"><a href="{{ route('brands.show', $brand) }}">{{ $brand->name }}</a></td>
            <td class="px-4 py-4">{{ $brand->business_model }}</td><td class="px-4 py-4">{{ $brand->inventory_policy }}</td><td class="px-4 py-4">{{ $brand->costing_method }}</td>
            <td class="whitespace-nowrap px-4 py-4">@include('brands._status')</td><td class="whitespace-nowrap px-4 py-4">{{ $brand->activated_at?->timezone(auth()->user()->organization->timezone)->format('d M Y H:i') ?? 'Belum diaktifkan' }}</td>
            <td class="min-w-44 px-4 py-4"><details><summary class="cursor-pointer font-medium">Aksi <span class="sr-only">{{ $brand->name }}</span></summary><div class="mt-3">@include('brands._actions')</div></details></td>
        </tr>@empty<tr><td colspan="8" class="px-6 py-16 text-center"><p class="text-lg font-semibold">{{ $counts['all'] ? 'Tidak ada brand yang cocok' : 'Belum ada brand' }}</p><p class="mt-2 text-stone-500">{{ $counts['all'] ? 'Ubah pencarian atau reset filter untuk melihat brand lainnya.' : 'Mulai dengan tombol Tambah Brand untuk membuat brand pertama.' }}</p></td></tr>@endforelse</tbody>
    </table></div>
    <div class="border-t border-stone-100 p-4">{{ $brands->links() }}</div>
</div>
@endsection
