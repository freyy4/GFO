@extends('app')
@section('title', 'Detail produk')
@section('page-heading', 'Detail produk')
@section('content')
@php($kind = 'products')
@php($routePrefix = 'products')
@php($record = $product)
<div class="space-y-6">
<section class="brand-panel space-y-5">
<div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-semibold">{{ $product->name }}</h2><p class="mt-1 font-mono text-sm text-slate-500">{{ $product->code }}</p></div>@include('product-recipes._status')</div>
@include('product-recipes._actions')
<dl class="grid gap-5 sm:grid-cols-2">
<div><dt class="text-sm text-slate-500">Brand</dt><dd class="mt-1">{{ $brand->name }}</dd></div>
<div><dt class="text-sm text-slate-500">Kategori</dt><dd class="mt-1">{{ $category?->name ?? 'Belum dipilih' }}</dd></div>
<div class="sm:col-span-2"><dt class="text-sm text-slate-500">Deskripsi</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $product->description ?: 'Belum ada deskripsi.' }}</dd></div>
</dl>
</section>
<section class="brand-panel space-y-4"><h2 class="text-lg font-semibold">Varian produk</h2><p class="text-sm text-slate-500">{{ $variantCount }} varian, termasuk yang diarsipkan.</p>
@if($variantCount === 0)<p>Belum ada varian. Tambahkan varian untuk menentukan SKU, harga jual, dan item stok.</p>@endif
<div class="flex flex-wrap gap-4"><a class="brand-link" href="{{ route('products.variants.index', $product) }}">Lihat daftar varian</a>
@if(!$product->trashed())@can('create', \App\Models\ProductVariant::class)<a class="brand-primary" href="{{ route('products.variants.create', $product) }}">Tambah varian</a>@endcan @endif
</div></section>
<a class="brand-link" href="{{ route('products.index') }}">Kembali ke daftar produk</a>
</div>
@endsection
