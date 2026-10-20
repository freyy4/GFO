@csrf
<section class="brand-panel space-y-5">
    <h2 class="text-lg font-semibold">Informasi kategori</h2><p class="brand-help">Kategori dikelola per brand. Kolom bertanda * wajib diisi.</p>
    @if($brands->isEmpty())<div role="status" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">Belum ada brand yang tersedia. Brand diperlukan untuk membuat kategori.</div>@endif
    <div class="grid gap-5 sm:grid-cols-2">
        <div><label class="brand-label" for="brand_id">Brand *</label><select class="brand-input" id="brand_id" name="brand_id" required @disabled($category->exists) aria-invalid="{{ $errors->has('brand_id') ? 'true' : 'false' }}" aria-describedby="brand-error"><option value="">Pilih brand</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string) old('brand_id', $category->brand_id) === (string) $brand->id)>{{ $brand->name }}{{ $brand->trashed() ? ' (diarsipkan)' : '' }}</option>@endforeach</select>@if($category->exists)<input type="hidden" name="brand_id" value="{{ $category->brand_id }}"><p class="brand-help">Brand tidak dapat dipindahkan setelah kategori dibuat.</p>@endif<p id="brand-error" class="brand-error">@error('brand_id'){{ $message }}@enderror</p></div>
        <div><label class="brand-label" for="parent_id">Kategori induk</label><select class="brand-input" id="parent_id" name="parent_id" aria-invalid="{{ $errors->has('parent_id') ? 'true' : 'false' }}" aria-describedby="parent-help parent-error"><option value="">Tanpa induk</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" data-brand="{{ $parent->brand_id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parent->id)>{{ $parent->name }} · {{ $parent->brand->name }}</option>@endforeach</select><p class="brand-help" id="parent-help">Induk harus berada pada brand yang sama dan tidak membentuk siklus.</p><p id="parent-error" class="brand-error">@error('parent_id'){{ $message }}@enderror</p></div>
        @foreach(['name' => ['Nama kategori', true], 'slug' => ['Slug', false]] as $field => [$label, $required])
        <div><label class="brand-label" for="{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label><input class="brand-input" id="{{ $field }}" name="{{ $field }}" maxlength="255" @required($required) value="{{ old($field, $category->$field) }}" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="{{ $field }}-error"><p id="{{ $field }}-error" class="brand-error">@error($field){{ $message }}@enderror</p>@if($field === 'slug')<p class="brand-help">Kosongkan untuk membuat slug dari nama; unik dalam brand.</p>@endif</div>
        @endforeach
        <div><label class="brand-label" for="sort_order">Urutan *</label><input class="brand-input" type="number" id="sort_order" name="sort_order" min="0" max="4294967295" step="1" required value="{{ old('sort_order', $category->sort_order) }}" aria-invalid="{{ $errors->has('sort_order') ? 'true' : 'false' }}" aria-describedby="sort-error"><p id="sort-error" class="brand-error">@error('sort_order'){{ $message }}@enderror</p></div>
        <div><label class="brand-label" for="is_active">Status *</label><select class="brand-input" id="is_active" name="is_active" required aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}" aria-describedby="status-error"><option value="1" @selected((string) old('is_active', (int) $category->is_active) === '1')>Aktif</option><option value="0" @selected((string) old('is_active', (int) $category->is_active) === '0')>Nonaktif</option></select><p id="status-error" class="brand-error">@error('is_active'){{ $message }}@enderror</p></div>
    </div>
</section>
<div class="flex items-center gap-4"><button type="submit" class="brand-primary" @disabled($brands->isEmpty())>Simpan kategori</button><a class="brand-link" href="{{ $category->exists ? route('product-categories.show', $category) : route('product-categories.index') }}">Batal</a></div>
<script>
    (() => {
        const brand = document.getElementById('brand_id');
        const parent = document.getElementById('parent_id');
        function filterParents() {
            [...parent.options].forEach(option => {
                if (!option.value) return;
                option.hidden = option.dataset.brand !== brand.value;
                option.disabled = option.hidden;
                if (option.selected && option.disabled) parent.value = '';
            });
        }
        brand.addEventListener('change', filterParents);
        filterParents();
    })();
</script>
