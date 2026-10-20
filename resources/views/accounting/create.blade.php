@extends('app')
@section('title', 'Tambah '.$label)
@section('page-heading', 'Tambah '.$label)
@section('content')
<div class="space-y-6">@include('accounting._navigation')
<form class="space-y-6" method="POST" action="{{ route($module.'.store') }}" data-confirm="Simpan dokumen ini? Pastikan unit, periode, dan nilai sudah benar.">@include('accounting._form')</form></div>
@endsection
