@extends('app')
@section('title', 'Tambah '.$label)
@section('page-heading', 'Tambah '.$label)
@section('content')
<div class="space-y-6">@include('purchasing._navigation')<form method="POST" action="{{ route($module.'.store') }}" class="space-y-6">@include('purchasing._form')</form></div>
@endsection
