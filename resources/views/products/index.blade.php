@extends('app')
@section('title', 'Produk & Varian')
@section('page-heading', 'Produk & Varian')
@section('page-description', 'Kelola produk sesuai cakupan akses Anda.')
@section('content')
@php($kind = 'products')
@php($routePrefix = 'products')
@include('product-recipes._index', ['records' => $products, 'label' => 'produk'])
@endsection
