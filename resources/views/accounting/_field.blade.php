@php
[$caption,$type,$required] = $definition;
$value = old($errorKey, $default ?? '');
if (is_bool($value)) $value = (int) $value;
if ($value instanceof \DateTimeInterface) $value = $type === 'datetime-local' ? $value->copy()->timezone($timezone)->format('Y-m-d\TH:i') : $value->format('Y-m-d');
if ($type === 'time' && is_string($value)) $value = substr($value, 0, 5);
if (!is_scalar($value) && $value !== null) $value = '';
@endphp
<div><label class="brand-label" for="{{ $id }}">{{ $caption }}{{ $required ? ' *' : '' }}</label>
@if($type === 'select')
<select class="brand-input" id="{{ $id }}" name="{{ $name }}" @required($required) aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}" aria-describedby="{{ $id }}-error"><option value="">Pilih {{ $caption }}</option>@foreach($options[$key] ?? [] as $option => $text)<option value="{{ $option }}" @selected((string)$value === (string)$option)>{{ $text }}</option>@endforeach</select>
@elseif($type === 'textarea')
<textarea class="brand-input" rows="3" id="{{ $id }}" name="{{ $name }}" maxlength="3000" aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}" @required($required) aria-describedby="{{ $id }}-error">{{ $value }}</textarea>
@else
<input class="brand-input" type="{{ in_array($type,['money','decimal','optional-decimal','signed-decimal','number','number-zero']) ? 'number' : $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @required($required) @if(in_array($type,['money','decimal','optional-decimal','signed-decimal','number','number-zero'])) @if($type !== 'signed-decimal') min="0" @endif step="{{ in_array($type, ['number','number-zero']) ? '1' : ($type === 'money' ? '0.01' : '0.0001') }}" @endif aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}" aria-describedby="{{ $id }}-error">
@endif
<p id="{{ $id }}-error" class="brand-error">@error($errorKey){{ $message }}@enderror</p></div>
