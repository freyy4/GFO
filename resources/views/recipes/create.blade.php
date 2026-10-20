@extends('app')
@section('title', 'Tambah recipe')
@section('page-heading', 'Tambah recipe')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
    @php($kind = 'recipes')
    @php($routePrefix = 'recipes')
    <form method="POST" action="{{ route('recipes.store', []) }}" class="space-y-6">
        @csrf
        @include('recipes._form')
        <div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan recipe</button><a
                class="brand-link" href="{{ route('recipes.index', []) }}">Batal</a></div>
    </form>
@endsection
