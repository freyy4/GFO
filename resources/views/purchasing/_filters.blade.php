<form method="GET" action="{{ route($module.'.index') }}" class="brand-panel grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3">
<div><label for="search" class="brand-label">Cari {{ $label }}</label><input class="brand-input" id="search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Nomor dokumen / nama barang"></div>
@if(\App\Support\PurchasingDefinition::document($module) || $module === 'purchase-requirements')
<div><label for="destination_unit_id" class="brand-label">Unit tujuan {{ $module === 'purchase-requirements' ? '*' : '' }}</label><select class="brand-input" id="destination_unit_id" name="destination_unit_id" @required($module === 'purchase-requirements')><option value="">{{ $module === 'purchase-requirements' ? 'Pilih unit untuk menghitung kebutuhan' : 'Semua unit yang diizinkan' }}</option>@foreach($options['destination_unit_id'] as $id => $text)<option value="{{ $id }}" @selected((string)($filters['destination_unit_id'] ?? '') === (string)$id)>{{ $text }}</option>@endforeach</select></div>
@endif
@if(!in_array($module, ['purchase-requirements','purchase-requisitions']))
<div><label for="supplier_id" class="brand-label">Supplier</label><select class="brand-input" id="supplier_id" name="supplier_id"><option value="">Semua supplier</option>@foreach($options['supplier_id'] as $id => $text)<option value="{{ $id }}" @selected((string)($filters['supplier_id'] ?? '') === (string)$id)>{{ $text }}</option>@endforeach</select></div>
@endif
@if($module !== 'purchase-requirements')
@php($statuses = match($module) {'supplier-items' => ['ACTIVE','INACTIVE'], 'supplier-price-history' => ['CURRENT','FUTURE','EXPIRED'], 'supplier-contracts' => ['DRAFT','ACTIVE','INACTIVE'], 'goods-receipts' => ['DRAFT','CONFIRMED','CANCELLED'], default => ['DRAFT','SUBMITTED','APPROVED','REJECTED','PARTIALLY_RECEIVED','RECEIVED','CANCELLED']})
<div><label for="status" class="brand-label">Status</label><select class="brand-input" id="status" name="status"><option value="">Semua status</option>@foreach($statuses as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
@foreach(['from' => 'Tanggal awal', 'to' => 'Tanggal akhir'] as $key => $caption)<div><label for="{{ $key }}" class="brand-label">{{ $caption }}</label><input type="date" class="brand-input" id="{{ $key }}" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}"></div>@endforeach
@endif
@if($module === 'purchase-orders')<div><label for="archived" class="brand-label">Arsip PO</label><select class="brand-input" id="archived" name="archived">@foreach(['no' => 'Tidak diarsipkan', 'yes' => 'Diarsipkan', 'all' => 'Semua'] as $key => $caption)<option value="{{ $key }}" @selected(($filters['archived'] ?? 'no') === $key)>{{ $caption }}</option>@endforeach</select></div>@endif
<div class="flex items-center gap-4"><button type="submit" class="brand-primary">Terapkan</button><a class="brand-link" href="{{ route($module.'.index') }}">Reset filter</a></div>
</form>
