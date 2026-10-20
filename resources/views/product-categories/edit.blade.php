@extends('app')
@section('title', 'Edit kategori')
@section('page-heading', 'Edit kategori')
@section('page-description', 'Kelola master data untuk organization Anda.')
@section('content')
<form method="POST" action="{{ route('product-categories.update', $category) }}" class="space-y-6" data-confirm="Simpan perubahan data dan status ini?">@method('PUT') @include('product-categories._form')</form>
@endsection
