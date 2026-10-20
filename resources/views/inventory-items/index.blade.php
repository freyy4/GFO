@extends('app')
@section('title', 'Bahan & Inventory Item')
@section('page-heading', 'Bahan & Inventory Item')
@section('page-description', 'Kelola katalog sesuai organization dan akses unit Anda.')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><p class="text-sm text-slate-500">Data ditampilkan sesuai cakupan akses Anda.</p>@if($abilities['create'])<a href="{{ route('inventory-items.create') }}" class="brand-primary">Tambah item</a>@endif</div>
<form method="GET" action="{{ route('inventory-items.index') }}" class="brand-panel grid gap-4 sm:grid-cols-2 xl:grid-cols-4" role="search">
<div><label for="search" class="brand-label">Pencarian</label><input type="search" id="search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="SKU atau nama item" class="brand-input"></div>
<div><label for="brand_id" class="brand-label">Brand</label><select id="brand_id" name="brand_id" class="brand-input"><option value="">Semua brand</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string) ($filters['brand_id'] ?? '') === (string) $brand->id)>{{ $brand->name }}</option>@endforeach</select></div>
<div><label for="status" class="brand-label">Status</label><select id="status" name="status" class="brand-input"><option value="">Semua status</option>@foreach(['ACTIVE' => 'Aktif', 'INACTIVE' => 'Nonaktif', '$item->trashed()' => 'Diarsipkan'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="item_type" class="brand-label">Jenis item</label><select id="item_type" name="item_type" class="brand-input"><option value="">Semua jenis</option>@foreach($types as $value => $label)<option value="{{ $value }}" @selected(($filters['item_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="flex items-end gap-4"><button type="submit" class="brand-primary">Terapkan</button><a href="{{ route('inventory-items.index') }}" class="brand-link">Reset</a></div>
</form>
<section class="brand-panel"><p class="mb-4 text-sm text-slate-500" role="status">{{ $items->total() }} data ditemukan</p>
@if($items->isEmpty())<div class="admin-empty">@include('_admin-icon', ['icon' => 'grid'])<h2 class="font-semibold">Belum ada item yang cocok</h2><p>Coba ubah pencarian atau filter, atau tambahkan data baru jika memiliki akses.</p><a href="{{ route('inventory-items.index') }}" class="brand-link">Reset pencarian</a></div>
@else
<div class="overflow-x-auto" role="region" aria-label="Daftar Bahan & Inventory Item" tabindex="0"><table class="w-full text-left text-sm"><caption class="sr-only">Daftar Bahan & Inventory Item sesuai akses</caption><thead><tr class="border-b border-slate-200"><th scope="col" class="px-3 py-3">SKU</th><th scope="col" class="px-3 py-3">Nama</th><th scope="col" class="px-3 py-3">Brand</th><th scope="col" class="px-3 py-3">Jenis</th><th scope="col" class="px-3 py-3">Status</th><th scope="col" class="px-3 py-3">Aksi</th></tr></thead><tbody>
@foreach($items as $item)<tr class="border-b border-slate-100"><th scope="row" class="px-3 py-4"><a class="brand-link" href="{{ route('inventory-items.show', $item) }}">{{ $item->sku }}</a></th><td class="px-3 py-4">{{ $item->name }}</td><td class="px-3 py-4">{{ $item->brand?->name ?? 'Bersama dalam organization' }}</td><td class="px-3 py-4">{{ $types[$item->item_type] ?? $item->item_type }}</td><td class="px-3 py-4">@include('_catalog-status', ['active' => $item->is_active, 'archived' => $item->trashed()])</td><td class="px-3 py-4"><div class="flex flex-wrap items-center gap-3"><a class="brand-link" href="{{ route('inventory-items.show', $item) }}" aria-label="Detail {{ $item->name }}">Detail</a>@include('inventory-items._actions')</div></td></tr>@endforeach
</tbody></table></div><div class="mt-5">{{ $items->links() }}</div>
@endif</section>
@endsection
