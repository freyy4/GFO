@extends('app')
@section('title', 'Edit inventory item')
@section('page-heading', 'Edit inventory item')
@section('page-description', 'Kelola master data untuk organization Anda.')
@section('content')
<form method="POST" action="{{ route('inventory-items.update', $item) }}" class="space-y-6" data-confirm="Simpan perubahan data dan status ini?">@method('PUT') @include('inventory-items._form')</form>
@endsection
