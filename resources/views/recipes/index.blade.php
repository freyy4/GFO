@extends('app')
@section('title', 'Recipe & BOM')
@section('page-heading', 'Recipe & BOM')
@section('page-description', 'Kelola recipe sesuai cakupan akses Anda.')
@section('content')
    @php($kind = 'recipes')
    @php($routePrefix = 'recipes')
    @include('product-recipes._index', ['records' => $recipes, 'label' => 'recipe'])
@endsection
