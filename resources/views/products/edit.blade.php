@extends('app')
@section('title', 'Edit produk')
@section('page-heading', 'Edit produk')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
@php($kind = 'products')
@php($routePrefix = 'products')
<form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6" data-confirm="Simpan perubahan ini?">
@csrf
@method('PUT')
@include('products._form')
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan produk</button><a class="brand-link" href="{{ route('products.show', $product) }}">Batal</a></div>
</form>
@endsection
