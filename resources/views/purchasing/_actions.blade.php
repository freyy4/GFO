<div class="flex flex-wrap items-center gap-3 text-sm">
<a class="brand-link" href="{{ route($module.'.show', $record) }}">Detail</a>
@can('purchasing-action', [$module, $record, 'update'])<a class="brand-link" href="{{ route($module.'.edit', $record) }}">Edit</a>@endcan
@foreach($actions as $action => $caption)
@can('purchasing-action', [$module, $record, $action])
<form method="POST" action="{{ route($module.'.'.$action, $record) }}" data-confirm="{{ $caption }}? Tindakan ini dicatat dalam audit dan dapat mengunci dokumen." class="flex flex-wrap items-center gap-2">@csrf @method('PATCH')
@if($action === 'close')<label class="text-xs">Akhir periode <input type="date" name="effective_until" class="brand-input" required min="{{ max(now($timezone)->format('Y-m-d'), $record->effective_from->format('Y-m-d')) }}" aria-label="Tanggal akhir periode harga"></label>@endif
<button type="submit" class="brand-link">{{ $caption }}</button></form>
@endcan
@endforeach
@can('purchasing-action', [$module, $record, 'delete'])<form method="POST" action="{{ route($module.'.destroy', $record) }}" data-confirm="Hapus data purchasing ini? Hanya dokumen yang belum digunakan dapat dihapus.">@csrf @method('DELETE')<button type="submit" class="brand-link text-red-700">Hapus</button></form>@endcan
@if($module === 'purchase-orders' && in_array($record->status, ['APPROVED','PARTIALLY_RECEIVED']))
@can('purchasing-create', 'goods-receipts')<a class="brand-primary" href="{{ route('goods-receipts.create', ['purchase_order_id' => $record->id]) }}">Terima barang</a>@endcan
@endif
</div>
