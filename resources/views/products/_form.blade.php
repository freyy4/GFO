<section class="brand-panel grid gap-5 sm:grid-cols-2">
@include('product-recipes._field', ['field' => 'brand_id', 'label' => 'Brand', 'type' => 'select', 'options' => $brands->pluck('name', 'id'), 'value' => $product->brand_id, 'required' => true, 'locked' => $product->exists, 'help' => 'Brand tidak dapat dipindahkan setelah produk dibuat.'])
@include('product-recipes._field', ['field' => 'product_category_id', 'label' => 'Kategori', 'type' => 'select', 'options' => $categories->mapWithKeys(fn ($c) => [$c->id => $c->name]), 'value' => $product->product_category_id, 'help' => 'Pilih kategori pada brand yang sama.'])
@include('product-recipes._field', ['field' => 'code', 'label' => 'Kode produk', 'value' => $product->code, 'max' => 60, 'required' => true, 'help' => 'Unik dalam brand, termasuk arsip. Huruf, angka, titik, underscore, dan tanda hubung.'])
@include('product-recipes._field', ['field' => 'name', 'label' => 'Nama produk', 'value' => $product->name, 'required' => true])
@include('product-recipes._field', ['field' => 'is_active', 'label' => 'Status', 'type' => 'select', 'options' => [1 => 'Aktif', 0 => 'Nonaktif'], 'value' => (int) $product->is_active, 'required' => true])
@include('product-recipes._field', ['field' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'value' => $product->description])
</section>
<script>
(() => {
const brands = @json($categories->pluck('brand_id', 'id'));
const brand = document.getElementById('brand_id'), category = document.getElementById('product_category_id');
function filter() { [...category.options].forEach(o => { if (!o.value) return; o.hidden = String(brands[o.value]) !== brand.value; o.disabled = o.hidden; if (o.disabled && o.selected) category.value = ''; }); }
brand.addEventListener('change', filter); filter();
})();
</script>
