@extends('app')
@section('title', 'Tambah varian')
@section('page-heading', 'Tambah varian')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
@php($kind = 'product-variants')
@php($routePrefix = 'products.variants')
@php($parent = $product)
<form method="POST" action="{{ route('products.variants.store', $product) }}" class="space-y-6">
@csrf
@include('product-variants._form')
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan varian</button><a class="brand-link" href="{{ route('products.variants.index', $product) }}">Batal</a></div>
</form>
@endsection
