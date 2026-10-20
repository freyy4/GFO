@csrf
<section class="brand-panel space-y-5"><h2 class="text-lg font-semibold">Informasi {{ $label }}</h2><p class="brand-help">Organization ditentukan dari akun. Kolom * wajib diisi. Waktu menggunakan {{ $timezone }}.</p>
@if($module === 'goods-receipts')<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Konfirmasi mencatat QC dan progres PO. Saldo stok tidak diposting melalui halaman ini.</p>@endif
@if($module === 'supplier-price-history')<p class="brand-help">Harga tidak boleh memiliki periode tumpang tindih untuk barang dan mata uang yang sama. Harga yang sudah berlaku dipertahankan; tutup periodenya lalu tambahkan harga baru.</p>@endif
<div class="grid gap-5 sm:grid-cols-2">
@foreach($fields as $key => [$caption, $type, $required])
@php
$default = $record->getAttribute($key);
if ($default instanceof \DateTimeInterface) $default = $type === 'datetime-local' ? $default->copy()->timezone($timezone)->format('Y-m-d\TH:i') : $default->format('Y-m-d');
$value = old($key, $default);
if (!is_scalar($value) && $value !== null) $value = '';
@endphp
<div @class(['sm:col-span-2' => $type === 'textarea'])><label class="brand-label" for="{{ $key }}">{{ $caption }}{{ $required ? ' *' : '' }}</label>
@if($type === 'select')<select class="brand-input" id="{{ $key }}" name="{{ $key }}" @required($required) aria-describedby="{{ $key }}-error" aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}"><option value="">Pilih {{ $caption }}</option>@foreach($options[$key] ?? [] as $id => $text)<option value="{{ $id }}" @selected((string)$value === (string)$id)>{{ $text }}</option>@endforeach</select>
@elseif($type === 'textarea')<textarea class="brand-input" id="{{ $key }}" name="{{ $key }}" rows="4" maxlength="10000" aria-describedby="{{ $key }}-error">{{ $value }}</textarea>
@else<input class="brand-input" type="{{ $type === 'decimal' ? 'number' : $type }}" id="{{ $key }}" name="{{ $key }}" value="{{ $value }}" @required($required) @if(in_array($type, ['decimal','number'])) min="0" step="{{ $type === 'number' ? '1' : (in_array($key, ['tax_amount','discount_amount']) ? '0.01' : '0.0001') }}" @endif @if($type === 'text') maxlength="{{ $key === 'currency' ? 3 : 100 }}" @endif aria-describedby="{{ $key }}-error" aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}">@endif
<p class="brand-error" id="{{ $key }}-error">@error($key){{ $message }}@enderror</p>
@if($key === 'purchase_order_id' && !$record->exists)<button type="button" class="brand-link mt-2 text-sm" id="load-po-lines" data-url="{{ route('goods-receipts.create') }}">Muat baris dari PO (mengganti isi form)</button>@endif
</div>
@endforeach
</div></section>
@if($lineFields)@include('purchasing._lines')@endif
<div class="flex flex-wrap items-center gap-4"><button type="submit" class="brand-primary">Simpan {{ $label }}</button><a class="brand-link" href="{{ $record->exists ? route($module.'.show', $record) : route($module.'.index') }}">Batal</a></div>
