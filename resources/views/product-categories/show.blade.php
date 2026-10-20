@extends('app')
@section('title', 'Detail kategori produk')
@section('page-heading', $category->name)
@section('page-description', 'Informasi kategori dan hierarki dalam brand.')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">@include('_catalog-status', ['active' => $category->is_active])<div class="flex flex-wrap items-center gap-4">@include('product-categories._actions')</div></div>
<section class="brand-panel"><h2 class="mb-5 text-lg font-semibold">Informasi kategori</h2><dl class="grid gap-6 sm:grid-cols-2">
@foreach(['Nama' => $category->name, 'Slug' => $category->slug, 'Brand' => $brand->name, 'Kategori induk' => $parent?->name ?? 'Tanpa induk', 'Urutan' => $category->sort_order, 'Diperbarui pada' => $category->updated_at?->format('d M Y H:i')] as $label => $value)<div><dt class="brand-label">{{ $label }}</dt><dd class="break-words">{{ $value ?? '—' }}</dd></div>@endforeach
</dl></section>
<p class="text-sm text-slate-500">Kategori yang memiliki anak atau digunakan produk tidak dapat dihapus. Gunakan status nonaktif bila kategori tidak lagi digunakan.</p>
<a href="{{ route('product-categories.index') }}" class="brand-link">Kembali ke daftar kategori</a>
@endsection
