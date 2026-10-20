@extends('app')
@section('title', 'Edit '.$label)
@section('page-heading', 'Edit '.$label)
@section('content')
<div class="space-y-6">@include('purchasing._navigation')<form method="POST" action="{{ route($module.'.update', $record) }}" class="space-y-6">@method('PUT')@include('purchasing._form')</form></div>
@endsection
