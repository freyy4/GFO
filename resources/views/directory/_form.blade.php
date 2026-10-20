@csrf
<section class="brand-panel space-y-5">
    <h2 class="text-lg font-semibold">Informasi {{ $label }}</h2>
    <p class="brand-help">Organization: {{ auth()->user()->organization->name }}. Kolom bertanda * wajib diisi.</p>
    <div class="grid gap-5 sm:grid-cols-2">
    @foreach($fields as $key => [$fieldLabel, $type, $required, $limit])
        @php
            $default = $record->getAttribute($key);
            if ($type === 'json' && $default !== null) $default = json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($type === 'date' && $default) $default = $default->format('Y-m-d');
            $value = old($key, $default);
            if (!is_scalar($value) && $value !== null) $value = '';
            $choices = $type === 'select' ? array_combine($limit, $limit) : ($options[$key] ?? []);
        @endphp
        <div @class(['sm:col-span-2' => in_array($type, ['textarea', 'json'])])>
            <label class="brand-label" for="{{ $key }}">{{ $fieldLabel }}{{ $required ? ' *' : '' }}</label>
            @if(in_array($type, ['select', 'reference']))
                <select class="brand-input" id="{{ $key }}" name="{{ $key }}" @required($required) aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}" aria-describedby="{{ $key }}-help {{ $key }}-error"><option value="">{{ $required ? 'Pilih '.$fieldLabel : 'Tidak dipilih' }}</option>@foreach($choices as $id => $text)<option value="{{ $id }}" @selected((string)$value === (string)$id)>{{ $text }}</option>@endforeach</select>
            @elseif(in_array($type, ['textarea', 'json']))
                <textarea class="brand-input" id="{{ $key }}" name="{{ $key }}" rows="4" maxlength="{{ $type === 'json' ? 10000 : $limit }}" @required($required) aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}" aria-describedby="{{ $key }}-help {{ $key }}-error">{{ $value }}</textarea>
            @else
                <input class="brand-input" id="{{ $key }}" name="{{ $key }}" type="{{ $type === 'decimal' ? 'number' : $type }}" value="{{ $value }}" @required($required) @if(is_int($limit)) maxlength="{{ $limit }}" @endif @if(in_array($type, ['decimal', 'number'])) min="0" step="{{ $type === 'number' ? '1' : ($key === 'performance_score' ? '0.0001' : '0.01') }}" @endif aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}" aria-describedby="{{ $key }}-help {{ $key }}-error">
            @endif
            <p id="{{ $key }}-help" class="brand-help">@switch($key)
                @case('code') @case('employee_no') Gunakan huruf, angka, garis bawah, atau tanda hubung. Harus unik dalam organization, termasuk data arsip. @break
                @case('home_unit_id') Unit asal karyawan menentukan cakupan akses data. @break
                @case('user_id') Opsional. Satu akun hanya boleh terhubung ke satu karyawan, termasuk data arsip. @break
                @case('required_skills') Opsional. Masukkan array JSON, misalnya ["Memasak", "Pelayanan"]. @break
                @case('emergency_contact') Opsional. Contoh: {"nama":"Kontak keluarga","telepon":"08123456789"}. @break
                @case('base_salary') Nilai gaji pokok, maksimal 2 angka desimal. Tidak memproses payroll. @break
                @case('default_lead_time_hours') Waktu standar pengiriman dalam jam; 0 jika belum ditentukan. @break
                @case('performance_score') Opsional. Rentang 0–9999.9999, maksimal 4 angka desimal. @break
                @case('ended_on') Tidak boleh sebelum tanggal bergabung. @break
                @default {{ $required ? 'Wajib diisi.' : 'Opsional; boleh dikosongkan.' }}
            @endswitch</p>
            <p id="{{ $key }}-error" class="brand-error">@error($key){{ $message }}@enderror</p>
        </div>
    @endforeach
    </div>
</section>
<div class="flex flex-wrap items-center gap-4"><button type="submit" class="brand-primary">Simpan {{ $label }}</button><a class="brand-link" href="{{ $record->exists ? route($module.'.show', $record) : route($module.'.index') }}">Batal</a></div>
