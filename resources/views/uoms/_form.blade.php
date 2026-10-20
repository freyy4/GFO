@csrf
<section class="brand-panel space-y-5">
    <div>
        <h2 class="text-lg font-semibold">Definisi satuan</h2>
        <p class="brand-help">Katalog global digunakan bersama oleh seluruh organization dan unit. Kolom bertanda * wajib
            diisi.</p>
    </div>
    @if ($inUse)
        <div role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Satuan telah
            digunakan dan dilindungi. Buat satuan baru jika definisinya perlu berbeda.</div>
    @endif
    <fieldset class="grid gap-5 sm:grid-cols-2" @disabled($inUse)>
        <legend class="sr-only">Informasi satuan</legend>
        @foreach (['code' => ['Kode satuan', 20, 'Contoh: KG, G, L, ML, PCS. Disimpan sebagai huruf kapital; unik secara global.'], 'name' => ['Nama satuan', 80, 'Nama yang mudah dikenali, misalnya Kilogram.']] as $field => [$label, $max, $help])
            <div><label for="{{ $field }}" class="brand-label">{{ $label }} *</label><input
                    id="{{ $field }}" name="{{ $field }}" class="brand-input" required
                    maxlength="{{ $max }}" value="{{ old($field, $uom->$field) }}"
                    aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}"
                    aria-describedby="{{ $field }}-help {{ $field }}-error">
                <p id="{{ $field }}-help" class="brand-help">{{ $help }}</p>
                <p id="{{ $field }}-error" class="brand-error">
                    @error($field)
                        {{ $message }}
                    @enderror
                </p>
            </div>
        @endforeach
        <div><label for="dimension" class="brand-label">Dimensi</label><input id="dimension" name="dimension"
                class="brand-input" maxlength="30" list="uom-dimensions"
                value="{{ old('dimension', $uom->dimension) }}"
                aria-invalid="{{ $errors->has('dimension') ? 'true' : 'false' }}"
                aria-describedby="dimension-help dimension-error"><datalist id="uom-dimensions">
                <option value="MASS">Massa</option>
                <option value="VOLUME">Volume</option>
                <option value="COUNT">Jumlah</option>
                <option value="LENGTH">Panjang</option>
            </datalist>
            <p id="dimension-help" class="brand-help">Opsional. Pilih atau isi pengelompokan satuan, misalnya MASS atau
                VOLUME.</p>
            <p id="dimension-error" class="brand-error">
                @error('dimension')
                    {{ $message }}
                @enderror
            </p>
        </div>
        <div><label for="decimal_places" class="brand-label">Jumlah desimal *</label><input id="decimal_places"
                name="decimal_places" type="number" min="0" max="4" step="1" class="brand-input"
                required value="{{ old('decimal_places', $uom->decimal_places) }}"
                aria-invalid="{{ $errors->has('decimal_places') ? 'true' : 'false' }}"
                aria-describedby="decimal-help decimal-error">
            <p id="decimal-help" class="brand-help">0–4 sesuai presisi kuantitas operasional. Contoh: PCS = 0, KG = 3.
            </p>
            <p id="decimal-error" class="brand-error">
                @error('decimal_places')
                    {{ $message }}
                @enderror
            </p>
        </div>
    </fieldset>
</section>
<div class="flex flex-wrap items-center gap-4">
    @if (!$inUse)
        <button type="submit" class="brand-primary">Simpan satuan</button>
    @endif
    <a href="{{ $uom->exists ? route('uoms.show', $uom) : route('uoms.index') }}"
        class="brand-link">{{ $inUse ? 'Kembali' : 'Batal' }}</a>
</div>
