@extends('app')
@section('title', 'Kategori produk')
@section('page-heading', 'Kategori produk')
@section('page-description', 'Kelola katalog sesuai organization dan akses unit Anda.')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><p class="text-sm text-slate-500">Data ditampilkan sesuai cakupan akses Anda.</p>@if($abilities['create'])<a href="{{ route('product-categories.create') }}" class="brand-primary">Tambah kategori</a>@endif</div>
<form method="GET" action="{{ route('product-categories.index') }}" class="brand-panel grid gap-4 sm:grid-cols-2 xl:grid-cols-4" role="search">
<div><label for="search" class="brand-label">Pencarian</label><input type="search" id="search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Nama atau slug kategori" class="brand-input"></div>
<div><label for="brand_id" class="brand-label">Brand</label><select id="brand_id" name="brand_id" class="brand-input"><option value="">Semua brand</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string) ($filters['brand_id'] ?? '') === (string) $brand->id)>{{ $brand->name }}</option>@endforeach</select></div>
<div><label for="status" class="brand-label">Status</label><select id="status" name="status" class="brand-input"><option value="">Semua status</option>@foreach(['ACTIVE' => 'Aktif', 'INACTIVE' => 'Nonaktif'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>

<div class="flex items-end gap-4"><button type="submit" class="brand-primary">Terapkan</button><a href="{{ route('product-categories.index') }}" class="brand-link">Reset</a></div>
</form>
<section class="brand-panel"><p class="mb-4 text-sm text-slate-500" role="status">{{ $categories->total() }} data ditemukan</p>
@if($categories->isEmpty())<div class="admin-empty">@include('_admin-icon', ['icon' => 'grid'])<h2 class="font-semibold">Belum ada kategori yang cocok</h2><p>Coba ubah pencarian atau filter, atau tambahkan data baru jika memiliki akses.</p><a href="{{ route('product-categories.index') }}" class="brand-link">Reset pencarian</a></div>
@else
<div class="overflow-x-auto" role="region" aria-label="Daftar Kategori produk" tabindex="0"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar Kategori produk sesuai akses</caption><thead><tr class="border-b border-slate-200"><th scope="col" class="px-3 py-3">Nama</th><th scope="col" class="px-3 py-3">Slug</th><th scope="col" class="px-3 py-3">Brand</th><th scope="col" class="px-3 py-3">Urutan</th><th scope="col" class="px-3 py-3">Status</th><th scope="col" class="px-3 py-3">Aksi</th></tr></thead><tbody>
@foreach($categories as $category)<tr class="border-b border-slate-100"><th scope="row" class="px-3 py-4"><a class="brand-link" href="{{ route('product-categories.show', $category) }}">{{ $category->name }}</a></th><td class="px-3 py-4">{{ $category->slug }}</td><td class="px-3 py-4">{{ $category->brand?->name ?? 'Bersama dalam organization' }}</td><td class="px-3 py-4">{{ $category->sort_order }}</td><td class="px-3 py-4">@include('_catalog-status', ['active' => $category->is_active, 'archived' => false])</td><td class="px-3 py-4"><div class="flex flex-wrap items-center gap-3"><a class="brand-link" href="{{ route('product-categories.show', $category) }}" aria-label="Detail {{ $category->name }}">Detail</a>@include('product-categories._actions')</div></td></tr>@endforeach
</tbody></table></div><div class="mt-5">{{ $categories->links() }}</div>
@endif</section>
@endsection
