@extends('operational-units.layout')
@section('title', 'Edit unit')
@section('content')
<h1 class="text-2xl font-semibold">Edit {{ $unit->name }}</h1>
<form method="POST" action="{{ route('operational-units.update', $unit) }}" class="space-y-6" data-confirm="Simpan perubahan informasi dan status unit ini?">@method('PUT') @include('operational-units._form')</form>
@endsection
