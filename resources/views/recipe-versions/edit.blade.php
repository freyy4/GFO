@extends('app')
@section('title', 'Edit versi')
@section('page-heading', 'Edit versi')
@section('page-description', 'Kolom bertanda * wajib diisi.')
@section('content')
@php($kind = 'recipe-versions')
@php($routePrefix = 'recipes.versions')
@php($parent = $recipe)
<form method="POST" action="{{ route('recipes.versions.update', [$recipe, $version]) }}" class="space-y-6" data-confirm="Simpan perubahan ini?">
@csrf
@method('PUT')
@include('recipe-versions._form')
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Simpan versi</button><a class="brand-link" href="{{ route('recipes.versions.show', [$recipe, $version]) }}">Batal</a></div>
</form>
@endsection
