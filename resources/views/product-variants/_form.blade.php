<p class="text-sm text-slate-500">Produk: {{ $product->name }}. SKU bersifat unik secara global mengikuti database.</p>
@if($inUse)<p class="brand-panel" role="status">Varian sudah digunakan. SKU dan relasi inventory dikunci.</p>@endif
<section class="brand-panel grid gap-5 sm:grid-cols-2">
@include('product-recipes._field', ['field' => 'sku', 'label' => 'SKU', 'value' => $variant->sku, 'max' => 80, 'required' => true, 'locked' => $inUse])
@include('product-recipes._field', ['field' => 'name', 'label' => 'Nama varian', 'value' => $variant->name, 'required' => true])
@include('product-recipes._field', ['field' => 'inventory_item_id', 'label' => 'Inventory item', 'type' => 'select', 'options' => $items->mapWithKeys(fn ($i) => [$i->id => $i->sku.' · '.$i->name]), 'value' => $variant->inventory_item_id, 'locked' => $inUse, 'help' => 'Item pada brand produk atau item bersama dalam organization.'])
@include('product-recipes._field', ['field' => 'selling_price', 'label' => 'Harga jual', 'value' => $variant->selling_price, 'required' => true, 'max' => 19, 'help' => 'Tidak negatif, maksimal 2 desimal. Gunakan titik sebagai pemisah.'])
@include('product-recipes._field', ['field' => 'minimum_margin_percent', 'label' => 'Margin minimum (%)', 'value' => $variant->minimum_margin_percent, 'max' => 8, 'help' => 'Opsional, 0–100 dengan maksimal 4 desimal.'])
@include('product-recipes._field', ['field' => 'is_active', 'label' => 'Status', 'type' => 'select', 'options' => [1 => 'Aktif', 0 => 'Nonaktif'], 'value' => (int) $variant->is_active, 'required' => true])
@include('product-recipes._field', ['field' => 'attributes_json', 'label' => 'Atribut varian', 'type' => 'textarea', 'value' => $variant->attributes ? json_encode($variant->attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '', 'help' => 'Opsional. Format objek JSON, contoh: {"Ukuran":"Besar","Rasa":"Pedas"}. Maksimal 20 atribut.'])
</section>
