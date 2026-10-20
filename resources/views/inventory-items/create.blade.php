@extends('app')
@section('title', 'Tambah inventory item')
@section('page-heading', 'Tambah inventory item')
@section('page-description', 'Kelola master data untuk organization Anda.')
@section('content')
<form method="POST" action="{{ route('inventory-items.store') }}" class="space-y-6">@include('inventory-items._form')</form>
@endsection
