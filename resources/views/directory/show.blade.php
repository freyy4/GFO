@extends('app')
@section('title', 'Detail '.$label)
@section('page-heading', 'Detail '.$label)
@section('page-description', $record->getAttribute($module === 'employees' ? 'full_name' : 'name'))
@section('content')
<div class="space-y-6">
<div class="brand-panel flex flex-wrap items-center justify-between gap-4">@include('directory._actions')@include('directory._status')</div>
<section class="brand-panel"><h2 class="mb-5 text-lg font-semibold">Informasi {{ $label }}</h2><dl class="grid gap-5 sm:grid-cols-2">
<div><dt class="text-sm text-stone-500">Organization</dt><dd class="mt-1">{{ auth()->user()->organization->name }}</dd></div>
@foreach($fields as $key => [$fieldLabel, $type])
@php($value = $record->getAttribute($key))
<div @class(['sm:col-span-2' => in_array($type, ['json', 'textarea'])])><dt class="text-sm text-stone-500">{{ $fieldLabel }}</dt><dd class="mt-1 break-words">
@if($type === 'json')<pre class="overflow-x-auto whitespace-pre-wrap rounded-lg bg-stone-50 p-3 text-sm">{{ $value !== null ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'Belum diisi' }}</pre>
@elseif($type === 'date'){{ $value?->format('d M Y') ?? '—' }}
@elseif($type === 'reference'){{ $options[$key][$value] ?? 'Tidak tersedia / belum dipilih' }}
@else{{ $value ?? '—' }}@endif
</dd></div>@endforeach
</dl></section>
<a class="brand-link" href="{{ route($module.'.index') }}">Kembali ke daftar {{ $label }}</a>
</div>
@endsection
