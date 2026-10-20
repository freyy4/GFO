<fieldset class="rounded-lg border border-stone-200 p-4" data-purchasing-line><legend class="px-2 text-sm font-semibold">Baris barang</legend><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
@foreach($lineFields as $key => [$caption, $type])
@php($field = 'lines.'.$index.'.'.$key)
@php($value = is_scalar($values[$key] ?? null) ? $values[$key] : ($type === 'decimal' && in_array($key, ['accepted_quantity','rejected_quantity','unit_price']) ? '0' : ''))
<div><label class="brand-label" for="line-{{ $index }}-{{ $key }}">{{ $caption }}{{ in_array($type, ['select','decimal']) ? ' *' : '' }}</label>
@if($type === 'select')<select class="brand-input" id="line-{{ $index }}-{{ $key }}" name="lines[{{ $index }}][{{ $key }}]" required aria-describedby="line-{{ $index }}-{{ $key }}-error"><option value="">Pilih {{ $caption }}</option>@foreach($options[$key] ?? [] as $id => $text)<option value="{{ $id }}" @selected((string)$value === (string)$id)>{{ $text }}</option>@endforeach</select>
@else<input class="brand-input" type="{{ $type === 'decimal' ? 'number' : $type }}" id="line-{{ $index }}-{{ $key }}" name="lines[{{ $index }}][{{ $key }}]" value="{{ $value }}" @if($type === 'decimal') required min="0" step="0.0001" @endif @if($type === 'text') maxlength="{{ $key === 'rejection_reason' ? 1000 : 100 }}" @endif aria-describedby="line-{{ $index }}-{{ $key }}-error">@endif
<p class="brand-error" id="line-{{ $index }}-{{ $key }}-error">@error($field){{ $message }}@enderror</p></div>
@endforeach
</div><button type="button" class="brand-link mt-3 text-sm text-red-700" data-remove-line>Hapus baris</button></fieldset>
