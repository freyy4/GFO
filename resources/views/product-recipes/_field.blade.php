@php($fieldId = str_replace(['.', '[', ']'], '-', $field))
<div>
<label class="brand-label" for="{{ $fieldId }}">{{ $label }}{{ ($required ?? false) ? ' *' : '' }}</label>
@if(($type ?? 'text') === 'select')
<select id="{{ $fieldId }}" name="{{ $field }}" class="brand-input" @required($required ?? false) @disabled($locked ?? false) aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="{{ $fieldId }}-help {{ $fieldId }}-error">
<option value="">{{ ($required ?? false) ? 'Pilih '.$label : 'Tanpa '.$label }}</option>
@foreach($options as $key => $text)<option value="{{ $key }}" @selected((string) old($field, $value ?? '') === (string) $key)>{{ $text }}</option>@endforeach
</select>
@if($locked ?? false)<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endif
@elseif(($type ?? 'text') === 'textarea')
<textarea id="{{ $fieldId }}" name="{{ $field }}" class="brand-input" rows="4" maxlength="{{ $max ?? 10000 }}" @required($required ?? false) aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="{{ $fieldId }}-help {{ $fieldId }}-error">{{ old($field, $value ?? '') }}</textarea>
@else
<input id="{{ $fieldId }}" name="{{ $field }}" type="{{ $type ?? 'text' }}" class="brand-input" maxlength="{{ $max ?? 255 }}" @required($required ?? false) @readonly($locked ?? false) value="{{ old($field, $value ?? '') }}" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="{{ $fieldId }}-help {{ $fieldId }}-error">
@endif
<p id="{{ $fieldId }}-help" class="brand-help">{{ $help ?? '' }}</p><p id="{{ $fieldId }}-error" class="brand-error">@error($field){{ $message }}@enderror</p>
</div>
