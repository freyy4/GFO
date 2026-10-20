@extends('brands.layout')
@section('title', 'Edit Brand')
@section('content')
<h1 class="text-3xl font-semibold">Edit Brand</h1>
<p class="text-stone-600">Kolom bertanda * wajib diisi. Status dikelola melalui tombol aktivasi dan nonaktifkan pada halaman detail.</p>
<form method="POST" action="{{ route('brands.update', $brand) }}" class="max-w-3xl space-y-6">@method('PUT')@include('brands._form')</form>
@endsection
