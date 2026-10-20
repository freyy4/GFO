@php($value = old($name, $default ?? ''))
<div>
<label class="brand-label" for="{{ $id ?? $name }}">{{ $label }}{{ ($required ?? true) ? ' *' : '' }}</label>
<input class="brand-input" id="{{ $id ?? $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ ($type ?? 'text') === 'password' ? '' : (is_scalar($value) ? $value : '') }}" autocomplete="{{ $autocomplete ?? 'off' }}" maxlength="{{ $maxlength ?? 255 }}" @required($required ?? true) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" aria-describedby="{{ $id ?? $name }}-error">
<p id="{{ $id ?? $name }}-error" class="brand-error">@error($name){{ $message }}@enderror</p>
</div>
