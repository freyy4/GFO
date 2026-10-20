@foreach (['success' => ['Berhasil', 'check'], 'error' => ['Terjadi kesalahan', 'alert'], 'warning' => ['Perhatian', 'alert'], 'info' => ['Informasi', 'info']] as $kind => [$label, $icon])
    @if (session($kind))
        <div class="admin-flash admin-flash-{{ $kind }}"
            role="{{ in_array($kind, ['error', 'warning']) ? 'alert' : 'status' }}" data-flash>
            @include('_admin-icon')<div>
                <p class="font-semibold">{{ $label }}</p>
                <p>{{ session($kind) }}</p>
            </div>
            <button type="button" class="admin-icon-button" data-dismiss-flash
                aria-label="Tutup pesan {{ strtolower($label) }}">@include('_admin-icon', ['icon' => 'close'])</button>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="admin-flash admin-flash-error" role="alert" data-flash>
        @include('_admin-icon', ['icon' => 'alert'])<div>
            <p class="font-semibold">Periksa kembali data berikut:</p>
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="admin-icon-button" data-dismiss-flash
            aria-label="Tutup ringkasan validasi">@include('_admin-icon', ['icon' => 'close'])</button>
    </div>
@endif
