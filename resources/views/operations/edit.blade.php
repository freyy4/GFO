@extends('app')
@section('title', 'Edit '.$label)
@section('page-heading', 'Edit '.$label)
@section('content')
<div class="space-y-6">@include('operations._navigation')
<form class="space-y-6" method="POST" action="{{ route($module.'.update', $record) }}" data-confirm="Simpan perubahan dokumen?">@method('PUT') @include('operations._form')</form></div>
@endsection