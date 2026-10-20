@extends('app')
@section('title', 'Edit varian')
@section('page-heading', 'Edit varian')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
@php($kind = 'product-variants')
@php($routePrefix = 'products.variants')
@php($parent = $product)
<form method="POST" action="{{ route('products.variants.update', [$product, $variant]) }}" class="space-y-6" data-confirm="Simpan perubahan ini?">
@csrf
@method('PUT')
@include('product-variants._form')
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan varian</button><a class="brand-link" href="{{ route('products.variants.show', [$product, $variant]) }}">Batal</a></div>
</form>
@endsection
