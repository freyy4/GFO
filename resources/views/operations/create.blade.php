@extends('app')
@section('title', 'Tambah '.$label)
@section('page-heading', 'Tambah '.$label)
@section('content')
<div class="space-y-6">@include('operations._navigation')
<form class="space-y-6" method="POST" action="{{ route($module.'.store') }}" data-confirm="Simpan dokumen ini? Pastikan unit, barang, dan jumlah sudah benar.">@include('operations._form')</form></div>
@endsection
