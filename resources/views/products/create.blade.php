@extends('app')
@section('title', 'Tambah produk')
@section('page-heading', 'Tambah produk')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
@php($kind = 'products')
@php($routePrefix = 'products')
<form method="POST" action="{{ route('products.store', []) }}" class="space-y-6">
@csrf
@include('products._form')
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan produk</button><a class="brand-link" href="{{ route('products.index', []) }}">Batal</a></div>
</form>
@endsection
