@extends('app')
@section('title', 'Tambah satuan')
@section('page-heading', 'Tambah satuan')
@section('page-description', 'Tambahkan definisi satuan ke katalog bersama.')
@section('content')
    <form action="{{ route('uoms.store') }}" method="POST" class="space-y-6">@include('uoms._form')</form>
@endsection
