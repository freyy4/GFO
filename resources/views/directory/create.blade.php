@extends('app')
@section('title', 'Tambah '.$label)
@section('page-heading', 'Tambah '.$label)
@section('page-description', 'Lengkapi informasi master data organization Anda.')
@section('content')
<form method="POST" action="{{ route($module.'.store') }}" class="space-y-6">@include('directory._form')</form>
@endsection
