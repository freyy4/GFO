@if ($hasVersions)
    <p class="brand-panel" role="status">Recipe sudah memiliki versi. Relasi output dikunci untuk menjaga konsistensi
        BOM.</p>
@endif

<section class="brand-panel grid gap-5 sm:grid-cols-2">
    @include('product-recipes._field', [
        'field' => 'brand_id',
        'label' => 'Brand',
        'type' => 'select',
        'options' => $brands->pluck('name', 'id'),
        'value' => $recipe->brand_id,
        'required' => true,
        'locked' => $recipe->exists,
    ])
    @include('product-recipes._field', [
        'field' => 'code',
        'label' => 'Kode recipe',
        'value' => $recipe->code,
        'max' => 60,
        'required' => true,
        'help' => 'Kode unik dalam brand, termasuk arsip.',
    ])
    @include('product-recipes._field', [
        'field' => 'name',
        'label' => 'Nama recipe',
        'value' => $recipe->name,
        'required' => true,
    ])
    @include('product-recipes._field', [
        'field' => 'status',
        'label' => 'Status',
        'type' => 'select',
        'options' => ['ACTIVE' => 'Aktif', 'INACTIVE' => 'Nonaktif'],
        'value' => $recipe->status,
        'required' => true,
    ])
    @include('product-recipes._field', [
        'field' => 'product_variant_id',
        'label' => 'Varian output',
        'type' => 'select',
        'options' => $variants->mapWithKeys(fn($v) => [$v->id => $v->sku . ' · ' . $v->name]),
        'value' => $recipe->product_variant_id,
        'locked' => $hasVersions,
        'help' => 'Pilih minimal salah satu output: varian atau inventory item.',
    ])
    @include('product-recipes._field', [
        'field' => 'output_item_id',
        'label' => 'Inventory output',
        'type' => 'select',
        'options' => $items->mapWithKeys(fn($i) => [$i->id => $i->sku . ' · ' . $i->name]),
        'value' => $recipe->output_item_id,
        'locked' => $hasVersions,
        'help' => 'Jika keduanya dipilih, inventory output harus sesuai item varian.',
    ])
</section>
<script>
    (() => {
        const maps = {
            product_variant_id: @json($variants->mapWithKeys(fn($v) => [$v->id => $v->product->brand_id])),
            output_item_id: @json($items->pluck('brand_id', 'id'))
        };
        const brand = document.getElementById('brand_id');

        function filter() {
            Object.entries(maps).forEach(([id, values]) => {
                const select = document.getElementById(id);
                [...select.options].forEach(o => {
                    if (!o.value) return;
                    o.hidden = values[o.value] !== null && String(values[o.value]) !== brand.value;
                    o.disabled = o.hidden;
                    if (o.disabled && o.selected) select.value = '';
                });
            });
        }
        brand.addEventListener('change', filter);
        filter();
    })();
</script>
