@extends('app')
@section('title', 'Edit satuan')
@section('page-heading', 'Edit satuan ' . $uom->code)
@section('page-description', 'Perubahan berlaku pada katalog global.')
@section('content')
    <form action="{{ route('uoms.update', $uom) }}" method="POST" class="space-y-6"
        data-confirm="Simpan perubahan definisi satuan pada katalog global?">@method('PUT') @include('uoms._form')
    </form>
@endsection
