@extends('app')
@section('title', 'Detail '.$label)
@section('page-heading', 'Detail '.$label)
@section('content')
<div class="space-y-6">@include('trade._navigation')
<section class="brand-panel space-y-4"><div class="flex flex-wrap justify-between gap-4"><h2 class="font-semibold">{{ $record->number ?? $record->name ?? '#'.$record->id }}</h2>@include('trade._status')</div>
<div class="flex flex-wrap gap-4">
@can('trade-action', [$module,$record,'update'])<a class="brand-link" href="{{ route($module.'.edit', $record) }}">Edit</a>@endcan
@can('trade-action', [$module,$record,'delete'])<form method="POST" action="{{ route($module.'.destroy', $record) }}" data-confirm="Hapus data ini? Tindakan dicatat pada audit.">@csrf @method('DELETE')<button class="brand-link" type="submit">Hapus</button></form>@endcan
</div>
<dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
@foreach($record->attributesToArray() as $key => $value)
@if(!in_array($key,['id','organization_id','deleted_at','gateway_payload','consent','updated_at']) && !is_array($value))
<div><dt class="text-sm text-stone-500">{{ $fields[$key][0] ?? \Illuminate\Support\Str::headline($key) }}</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $options[$key][$value] ?? $value ?? '—' }}</dd></div>
@endif
@endforeach
@if(in_array($module,['inventory','inventory-balances']))<div><dt class="text-sm text-stone-500">Tersedia</dt><dd>{{ bcsub(bcsub($record->on_hand,$record->reserved,4),$record->quarantined,4) }}</dd></div>@endif
</dl></section>
@if($rows->isNotEmpty())<section class="brand-panel space-y-4"><h2 class="font-semibold">Rincian dokumen</h2>
@foreach($rows as $row)<dl class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($row->attributesToArray() as $key => $value)@if(!in_array($key,['id','created_at','updated_at','sales_order_id','stock_count_id','stock_transfer_id','daily_closing_id','inventory_reconciliation_id']) && !is_array($value))<div><dt class="text-sm text-stone-500">{{ $lineFields[$key][0] ?? \Illuminate\Support\Str::headline($key) }}</dt><dd class="break-words">{{ $options[$key][$value] ?? $value ?? '—' }}</dd></div>@endif @endforeach</dl>@endforeach
</section>@endif
@if($module === 'sales-payments' && is_array($record->gateway_payload))<section class="brand-panel"><p>Dibayar: {{ $currency }} {{ $record->gateway_payload['tendered_amount'] ?? $record->amount }}</p><p>Kembalian: {{ $currency }} {{ $record->gateway_payload['change'] ?? '0.00' }}</p></section>@endif
@if($module === 'sales-orders' && $record->status !== 'DRAFT')<section class="brand-panel"><h2 class="font-semibold">Pembayaran</h2>@foreach($record->salesPayments as $payment)<p>{{ $payment->method }} · {{ $currency }} {{ $payment->amount }} · Kembalian {{ $payment->gateway_payload['change'] ?? '0.00' }}</p>@endforeach</section>@endif
@if($module === 'sales-refunds')<p class="brand-help">Konfirmasi setelah dana benar-benar dikembalikan. Sistem mencatat refund manual dan tidak mengirim dana atau mengembalikan stok otomatis.</p>@endif
@if($module === 'stock-transfers')<p class="brand-help">Penerimaan mengonfirmasi seluruh jumlah yang dikirim. Jika ada selisih fisik, jangan konfirmasi sebelum pemeriksaan.</p>@endif
@foreach($actions as $action => $caption)
@can('trade-action', [$module,$record,$action])
<form class="brand-panel space-y-4" method="POST" action="{{ route($module.'.'.$action,$record) }}" data-confirm="{{ $caption }}? Pastikan data benar. Tindakan dapat mengubah saldo atau mengunci dokumen.">
@csrf @method('PATCH')
@if($action === 'checkout')
<p class="font-semibold">Total pembayaran: {{ $currency }} {{ $record->total_amount }}</p><p class="brand-help">Pembayaran non-tunai dicatat manual setelah terverifikasi pada provider. Tunai dapat melebihi total; selisih merupakan kembalian.</p>
<div class="grid gap-4 sm:grid-cols-3">
@foreach(['payment_method'=>['Metode','select',true],'tendered_amount'=>['Nominal dibayar','money',true],'reference_no'=>['Referensi non-tunai','text',false]] as $key=>$definition)
@include('trade._field',['name'=>$key,'id'=>$key,'errorKey'=>$key,'default'=>$key==='tendered_amount'?$record->total_amount:''])
@endforeach</div>
@endif
@if($action === 'close')@include('trade._field',['key'=>'closing_cash','name'=>'closing_cash','id'=>'closing_cash','errorKey'=>'closing_cash','definition'=>['Kas akhir fisik (termasuk modal)','money',true],'default'=>''])@endif
<button class="brand-primary" type="submit">{{ $caption }}</button>
</form>
@endcan
@endforeach
<a class="brand-link" href="{{ route($module.'.index') }}">Kembali ke {{ $label }}</a>
</div>
@endsection
