@extends('app')
@section('title', $label)
@section('page-heading', $label)
@section('content')
<div class="space-y-6">@include('accounting._navigation')@include('accounting._filters')
<p class="brand-help">Ringkasan berdasarkan unit berizin. Angka nol berarti belum ada transaksi yang memenuhi kriteria; ringkasan ini bukan saldo rekening bank.</p>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($summary as $caption=>$amount)@if(!in_array($caption,['from','to']))<section class="brand-panel"><h2 class="text-sm text-stone-600">{{ $caption }}</h2><p class="mt-3 break-words text-2xl font-semibold">Rp {{ \App\Support\AccountingDisplay::money((string)$amount) }}</p></section>@endif @endforeach</div></div>
@endsection
