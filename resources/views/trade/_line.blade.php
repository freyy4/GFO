<fieldset class="rounded-lg border border-stone-200 p-4" data-trade-line><legend class="px-2 font-semibold">Barang</legend><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
@foreach($lineFields as $key => $definition)
@include('trade._field', ['name' => 'lines['.$index.']['.$key.']', 'id' => 'line-'.$index.'-'.$key, 'errorKey' => 'lines.'.$index.'.'.$key, 'default' => $values[$key] ?? ''])
@endforeach
</div><button type="button" class="brand-link mt-3" data-remove-line>Hapus baris</button></fieldset>
