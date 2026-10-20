@extends('brands.layout')
@section('title', 'Tambah Brand')
@section('content')
<h1 class="text-3xl font-semibold">Tambah Brand</h1>
<p class="text-stone-600">Kolom bertanda * wajib diisi. Status dikelola melalui tombol aktivasi dan nonaktifkan pada halaman detail.</p>
<form method="POST" action="{{ route('brands.store') }}" class="max-w-3xl space-y-6">@include('brands._form')</form>
@endsection
