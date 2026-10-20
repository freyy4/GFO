@extends('app')
@section('title', 'Detail '.$label)
@section('page-heading', 'Detail '.$label)
@section('content')
<div class="space-y-6">@include('accounting._navigation')
<section class="brand-panel space-y-5"><div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">{{ $record->number ?? $record->bill_no ?? $record->name ?? $record->title ?? '#'.$record->id }}</h2>@include('accounting._status')</div>
<div class="flex gap-4">@can('accounting-action',[$module,$record,'update'])<a class="brand-link" href="{{ route($module.'.edit',$record) }}">Edit</a>@endcan
@can('accounting-action',[$module,$record,'delete'])<form method="POST" action="{{ route($module.'.destroy',$record) }}" data-confirm="Hapus data ini? Tindakan dicatat pada audit.">@csrf @method('DELETE')<button class="brand-link" type="submit">Hapus</button></form>@endcan</div>
<dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($record->attributesToArray() as $key=>$value)@if(!is_array($value) && !in_array($key,['id','organization_id','updated_at','targetable_type','measurable_type']))<div><dt class="text-sm text-stone-500">{{ $fields[$key][0] ?? \App\Support\AccountingDisplay::label($key) }}</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $options[$key][$value] ?? $value ?? '—' }}</dd></div>@endif @endforeach</dl>
@include('accounting._calculation')
</section>
@if($rows->isNotEmpty())<section class="brand-panel space-y-4"><h2 class="font-semibold">Rincian</h2>@foreach($rows as $row)<dl class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($row->attributesToArray() as $key=>$value)@if(!is_array($value)&&!in_array($key,['created_at','updated_at','payroll_run_id','payslip_id','source_type','source_id']))<div><dt class="text-sm text-stone-500">{{ \App\Support\AccountingDisplay::label($key) }}</dt><dd class="break-words">{{ $options[$key][$value] ?? $value ?? '—' }}</dd></div>@endif @endforeach</dl>@endforeach</section>@endif
@if($module === 'payroll-runs')<p role="status" class="brand-help">Perhitungan, persetujuan, dan pembayaran payroll menunggu formula perusahaan. Draft tidak menghasilkan slip atau pembayaran otomatis.</p>@endif
@foreach($actions as $action=>$caption)
@can('accounting-action',[$module,$record,$action])
@if($module !== 'payroll-runs' || $action === 'cancel')
<form class="brand-panel space-y-4" method="POST" action="{{ route($module.'.'.$action,$record) }}" data-confirm="{{ $caption }}? Periksa data dan referensi; tindakan ini dapat mengunci dokumen atau mengubah saldo.">
@csrf @method('PATCH')
@if($action === 'pay')
<p class="brand-help">Catat hanya pembayaran yang sudah dilakukan. Aplikasi tidak mengirim transfer bank. @if($module === 'bills')Pembayaran tagihan membuat expense terkait secara otomatis.@endif</p>
@foreach(['payment_method'=>['Metode pembayaran','select',true],'payment_reference'=>['Referensi pembayaran','text',true]] as $key=>$definition)@include('accounting._field',['name'=>$key,'id'=>$key,'errorKey'=>$key,'default'=>''])@endforeach
@endif
@if($action === 'generate')@include('accounting._field',['key'=>'billing_month','name'=>'billing_month','id'=>'billing_month','errorKey'=>'billing_month','definition'=>['Bulan tagihan','month',true],'default'=>''])@endif
<button class="brand-primary" type="submit">{{ $caption }}</button></form>
@endif
@endcan
@endforeach
<a class="brand-link" href="{{ route($module.'.index') }}">Kembali ke {{ $label }}</a>
</div>
@endsection
