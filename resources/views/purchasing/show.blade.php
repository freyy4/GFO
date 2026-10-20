@extends('app')
@section('title', 'Detail '.$label)
@section('page-heading', 'Detail '.$label)
@section('content')
<div class="space-y-6">@include('purchasing._navigation')
<section class="brand-panel space-y-4"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-mono text-lg font-semibold">{{ $record->number ?? $record->contract_no ?? '#'.$record->id }}</h2>@include('purchasing._status')</div>@include('purchasing._actions')</section>
@if($module === 'goods-receipts')<p class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">Penerimaan ini mencatat QC dan progres PO. Konfirmasi tidak memposting saldo stok.</p>@endif
<section class="brand-panel"><h2 class="mb-5 font-semibold">Informasi dokumen</h2><dl class="grid gap-5 sm:grid-cols-2">
@foreach($fields as $key => [$caption, $type])
@php($value = $record->getAttribute($key))
<div @class(['sm:col-span-2' => $type === 'textarea'])><dt class="text-sm text-stone-500">{{ $caption }}</dt><dd class="mt-1 whitespace-pre-wrap break-words">@if($type === 'select'){{ $options[$key][$value] ?? 'Tidak tersedia (#'.$value.')' }}@elseif($value instanceof \DateTimeInterface){{ $type === 'datetime-local' ? $value->copy()->timezone($timezone)->format('d M Y H:i') : $value->format('d M Y') }}@else{{ $value ?? '—' }}@endif</dd></div>
@endforeach
@if($module === 'purchase-orders')@foreach(['subtotal' => 'Subtotal', 'total_amount' => 'Total PO'] as $key => $caption)<div><dt class="text-sm text-stone-500">{{ $caption }}</dt><dd class="mt-1 font-semibold">{{ $record->currency }} {{ $record->$key }}</dd></div>@endforeach
@endif
@if($record->approved_at)<div><dt class="text-sm text-stone-500">Disetujui pada</dt><dd>{{ $record->approved_at->timezone($timezone)->format('d M Y H:i') }}</dd></div>@endif
</dl></section>
@if($lineFields)
<section class="overflow-hidden rounded-xl border border-stone-200 bg-white"><h2 class="p-5 font-semibold">Detail barang</h2><div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Baris {{ $label }}</caption><thead class="bg-stone-50"><tr><th scope="col" class="px-4 py-3">Item</th><th scope="col" class="px-4 py-3">Satuan</th>@foreach($lineFields as $key => [$caption, $type])@if($type !== 'select')<th scope="col" class="whitespace-nowrap px-4 py-3">{{ $caption }}</th>@endif @endforeach
@if($module === 'purchase-orders')<th scope="col" class="px-4 py-3">Lolos QC diterima</th><th scope="col" class="px-4 py-3">Total baris</th>@elseif($module === 'purchase-requisitions')<th scope="col" class="px-4 py-3">Tersedia saat dibuat</th><th scope="col" class="px-4 py-3">Kekurangan</th>@else<th scope="col" class="px-4 py-3">QC</th><th scope="col" class="px-4 py-3">Biaya satuan PO</th>@endif
</tr></thead><tbody class="divide-y divide-stone-100">
@foreach($lines as $line)<tr><td class="px-4 py-4">{{ $line->inventoryItem?->name ?? 'Item tidak tersedia' }}</td><td class="px-4 py-4">{{ $line->uom?->code }}</td>
@foreach($lineFields as $key => [$caption, $type])@if($type !== 'select')<td class="px-4 py-4">{{ $type === 'date' ? $line->$key?->format('d M Y') : ($line->$key ?? '—') }}</td>@endif @endforeach
@if($module === 'purchase-orders')<td class="px-4 py-4">{{ $line->received_quantity }}</td><td class="px-4 py-4">{{ $line->line_total }}</td>@elseif($module === 'purchase-requisitions')<td class="px-4 py-4">{{ $line->available_quantity_snapshot }}</td><td class="px-4 py-4">{{ $line->shortage_quantity }}</td>@else<td class="px-4 py-4">{{ $line->qc_status }}</td><td class="px-4 py-4">{{ $line->unit_cost }}</td>@endif
</tr>@endforeach
</tbody></table></div></section>
@endif
<a class="brand-link" href="{{ route($module.'.index') }}">Kembali ke daftar {{ $label }}</a>
</div>
@endsection
