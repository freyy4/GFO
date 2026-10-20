@extends('operational-units.layout')
@section('title', 'Tambah unit')
@section('content')
<h1 class="text-2xl font-semibold">Tambah unit operasional</h1>
<form method="POST" action="{{ route('operational-units.store') }}" class="space-y-6">@include('operational-units._form')</form>
@endsection
