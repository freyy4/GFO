@extends('app')
@section('title', 'Tambah versi')
@section('page-heading', 'Tambah versi')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
@php($kind = 'recipe-versions')
@php($routePrefix = 'recipes.versions')
@php($parent = $recipe)
<form method="POST" action="{{ route('recipes.versions.store', $recipe) }}" class="space-y-6">
@csrf
@include('recipe-versions._form')
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan versi</button><a class="brand-link" href="{{ route('recipes.versions.index', $recipe) }}">Batal</a></div>
</form>
@endsection
