@extends('app')
@section('title', 'Detail '.$label)
@section('page-heading', 'Detail '.$label)
@section('content')
<div class="space-y-6">@include('operations._navigation')
<section class="brand-panel space-y-5"><div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">{{ $record->number ?? $record->batch_no ?? $record->name ?? '#'.$record->id }}</h2>@include('operations._status')</div>
<div class="flex gap-4">@can('operations-action',[$module,$record,'update'])<a class="brand-link" href="{{ route($module.'.edit',$record) }}">Edit</a>@endcan
@can('operations-action',[$module,$record,'delete'])<form method="POST" action="{{ route($module.'.destroy',$record) }}" data-confirm="Hapus data ini? Tindakan dicatat pada audit.">@csrf @method('DELETE')<button class="brand-link" type="submit">Hapus</button></form>@endcan</div>
<dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($record->attributesToArray() as $key=>$value)@if(!is_array($value) && !in_array($key,['id','organization_id','updated_at']))<div><dt class="text-sm text-stone-500">{{ $fields[$key][0] ?? \Illuminate\Support\Str::headline($key) }}</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $options[$key][$value] ?? $value ?? '—' }}</dd></div>@endif @endforeach</dl>
</section>
@if($rows->isNotEmpty())<section class="brand-panel space-y-4"><h2 class="font-semibold">Rincian</h2>@foreach($rows as $row)<dl class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($row->attributesToArray() as $key=>$value)@if(!is_array($value)&&!in_array($key,['id','created_at','updated_at','demand_forecast_id','production_plan_id','production_batch_id','roster_id']))<div><dt class="text-sm text-stone-500">{{ $lineFields[$key][0] ?? \Illuminate\Support\Str::headline($key) }}</dt><dd class="break-words">{{ $options[$key][$value] ?? $value ?? '—' }}</dd></div>@endif @endforeach</dl>@endforeach</section>@endif
@if($module === 'packing-dispatch')<p class="brand-help">Penyelesaian tugas mencatat kesiapan dispatch. Gunakan Transfer Stok untuk pengiriman fisik.</p>@endif
@foreach($actions as $action=>$caption)
@can('operations-action',[$module,$record,$action])
<form class="brand-panel space-y-4" method="POST" action="{{ route($module.'.'.$action,$record) }}" data-confirm="{{ $caption }}? Periksa data sebelum melanjutkan; tindakan ini dapat mengunci dokumen atau mengubah stok/jadwal.">
@csrf @method('PATCH')
@if($module === 'production-batches' && $action === 'start')
<p class="brand-help">Catat bahan aktual yang digunakan. Bahan dikeluarkan mengikuti FIFO/FEFO dan tidak dapat dibatalkan setelah batch dimulai.</p>
@foreach($rows as $i=>$usage)<input type="hidden" name="usages[{{ $i }}][usage_id]" value="{{ $usage->id }}">
@include('operations._field',['key'=>'actual_quantity','name'=>'usages['.$i.'][actual_quantity]','id'=>'usage-'.$i,'errorKey'=>'usages.'.$i.'.actual_quantity','definition'=>[($options['inventory_item_id'][$usage->inventory_item_id] ?? 'Bahan #'.$usage->inventory_item_id).' — kuantitas aktual','decimal',true],'default'=>$usage->planned_quantity])
@endforeach
@include('operations._field',['key'=>'usage_reason','name'=>'usage_reason','id'=>'usage_reason','errorKey'=>'usage_reason','definition'=>['Catatan pemakaian aktual','textarea',true],'default'=>''])
@endif
@if($module === 'production-batches' && $action === 'complete')
@include('operations._field',['key'=>'actual_quantity','name'=>'actual_quantity','id'=>'actual_quantity','errorKey'=>'actual_quantity','definition'=>['Hasil aktual','decimal',true],'default'=>$record->planned_quantity])
@include('operations._field',['key'=>'expires_on','name'=>'expires_on','id'=>'expires_on','errorKey'=>'expires_on','definition'=>['Kedaluwarsa (wajib untuk LOT_EXPIRY)','date',false],'default'=>''])
@endif
<button class="brand-primary" type="submit">{{ $caption }}</button></form>
@endcan
@endforeach
<a class="brand-link" href="{{ route($module.'.index') }}">Kembali ke {{ $label }}</a>
</div>
@endsection
