@extends('app')
@section('title', 'Edit '.$label)
@section('page-heading', 'Edit '.$label)
@section('page-description', 'Lengkapi informasi master data organization Anda.')
@section('content')
<form method="POST" action="{{ route($module.'.update', $record) }}" class="space-y-6">@method('PUT')@include('directory._form')</form>
@endsection
