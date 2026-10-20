@extends('app')
@section('title', 'Edit recipe')
@section('page-heading', 'Edit recipe')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
    @php($kind = 'recipes')
    @php($routePrefix = 'recipes')
    <form method="POST" action="{{ route('recipes.update', $recipe) }}" class="space-y-6" data-confirm="Simpan perubahan ini?">
        @csrf
        @method('PUT')
        @include('recipes._form')
        <div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan recipe</button><a
                class="brand-link" href="{{ route('recipes.show', $recipe) }}">Batal</a></div>
    </form>
@endsection
