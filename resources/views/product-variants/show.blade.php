@extends('app')
@section('title', 'Detail varian')
@section('page-heading', 'Detail varian')
@section('content')
@php($kind = 'product-variants')
@php($routePrefix = 'products.variants')
@php($record = $variant)
@php($parent = $product)
<div class="space-y-6">
<section class="brand-panel space-y-5">
<div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-semibold">{{ $variant->name }}</h2><p class="mt-1 font-mono text-sm text-slate-500">{{ $variant->sku }}</p></div>@include('product-recipes._status')</div>
@include('product-recipes._actions')
@if($inUse)<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="status">Varian sudah digunakan. SKU dan relasi inventory dikunci untuk menjaga riwayat.</p>@endif
@if($product->trashed())<p class="brand-help">Produk induk diarsipkan. Perubahan varian tidak tersedia.</p>@endif
<dl class="grid gap-5 sm:grid-cols-2">
<div><dt class="text-sm text-slate-500">Produk</dt><dd class="mt-1"><a class="brand-link" href="{{ route('products.show', $product) }}">{{ $product->name }}</a></dd></div>
<div><dt class="text-sm text-slate-500">Harga jual</dt><dd class="mt-1 font-semibold">{{ auth()->user()->organization->currency }} {{ $variant->selling_price }}</dd></div>
<div><dt class="text-sm text-slate-500">Margin minimum</dt><dd class="mt-1">{{ $variant->minimum_margin_percent !== null ? $variant->minimum_margin_percent.'%' : 'Belum ditentukan' }}</dd></div>
<div><dt class="text-sm text-slate-500">Item stok</dt><dd class="mt-1">{{ $item ? $item->sku.' · '.$item->name : 'Belum terhubung atau item tidak tersedia' }}</dd></div>
</dl>
</section>
<section class="brand-panel space-y-4"><h2 class="text-lg font-semibold">Atribut varian</h2>
@if($variant->attributes)<dl class="grid gap-4 sm:grid-cols-2">@foreach($variant->attributes as $key => $value)<div><dt class="text-sm text-slate-500">{{ $key }}</dt><dd class="mt-1 break-words">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd></div>@endforeach</dl>
@else<p class="text-sm text-slate-500">Belum ada atribut varian.</p>@endif
</section>
<a class="brand-link" href="{{ route('products.variants.index', $product) }}">Kembali ke daftar varian</a>
</div>
@endsection
