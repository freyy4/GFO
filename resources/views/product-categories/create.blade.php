@extends('app')
@section('title', 'Tambah kategori')
@section('page-heading', 'Tambah kategori')
@section('page-description', 'Kelola master data untuk organization Anda.')
@section('content')
<form method="POST" action="{{ route('product-categories.store') }}" class="space-y-6">@include('product-categories._form')</form>
@endsection
