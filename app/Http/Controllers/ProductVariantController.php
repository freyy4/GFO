<?php
namespace App\Http\Controllers;
use App\Http\Requests\{ProductRecipeIndexRequest, SaveProductVariantRequest};
use App\Models\{Product, ProductVariant};
use App\Support\{CatalogAccess, ProductRecipeAccess as Access, ProductRecipeMutation as Mutation};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;
class ProductVariantController extends Controller
{
 public function index(ProductRecipeIndexRequest $request, Product $product): View
 {
 Gate::authorize('view', $product); $filters = $request->validated();
 $variants = Access::variants($request->user())->withTrashed()->where('product_id', $product->id)
 ->when(($filters['search'] ?? '') !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')->orWhere('sku', 'like', '%'.$filters['search'].'%')))
 ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'ARCHIVED' ? $q->onlyTrashed() : $q->whereNull('deleted_at')->where('is_active', $s === 'ACTIVE'))
 ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
 return view('product-variants.index', compact('product', 'variants', 'filters') + ['abilities' => CatalogAccess::abilities($request->user(), 'products')]);
 }
 public function create(Request $request, Product $product): View { Gate::authorize('view', $product); Gate::authorize('create', ProductVariant::class); return $this->form($request, $product, new ProductVariant(['is_active' => true, 'selling_price' => 0]), 'create'); }
 public function store(SaveProductVariantRequest $request, Product $product): RedirectResponse { return $this->save($request, $product, new ProductVariant); }
 public function update(SaveProductVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse { return $this->save($request, $product, $variant); }
 private function save(SaveProductVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
 {
 try {
 $variant = DB::transaction(function () use ($request, $product, $variant) {
 Mutation::lock($request); $product = Access::products($request->user())->lockForUpdate()->findOrFail($product->id); Gate::authorize('view', $product);
 if ($variant->exists) { $variant = Access::variants($request->user())->where('product_id', $product->id)->lockForUpdate()->findOrFail($variant->id); }
 Gate::authorize($variant->exists ? 'update' : 'create', $variant->exists ? $variant : ProductVariant::class);
 $data = $request->validated();
 if ($variant->exists && Mutation::variantUsed($variant)) {
 foreach (['sku', 'inventory_item_id'] as $field) { if ((string) ($data[$field] ?? '') !== (string) ($variant->$field ?? '')) { Mutation::reject($field, 'Identitas varian yang sudah digunakan tidak dapat diubah.'); } }
 }
 if (! empty($data['inventory_item_id'])) {
 $item = Access::items($request->user(), 'products')->withTrashed()->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $product->brand_id))->lockForUpdate()->find($data['inventory_item_id']);
 if (! $item || (($item->trashed() || ! $item->is_active) && $variant->inventory_item_id !== $item->id)) { Mutation::reject('inventory_item_id', 'Item harus aktif dan sesuai brand produk atau item bersama.'); }
 }
 if (ProductVariant::withTrashed()->whereRaw('LOWER(sku) = ?', [strtolower($data['sku'])])->when($variant->exists, fn ($q) => $q->whereKeyNot($variant->id))->exists()) { Mutation::reject('sku', 'SKU tidak tersedia. Gunakan SKU lain.'); }
 $before = $variant->toArray(); $event = $variant->exists ? 'update' : 'create';
 $data['attributes'] = ! empty($data['attributes_json']) ? json_decode($data['attributes_json'], true) : null; unset($data['attributes_json']);
 $variant->fill($data); $variant->product_id = $product->id; $variant->save(); Mutation::audit($request, $variant, 'product_variants.'.$event, $before); return $variant;
 }, 3);
 } catch (UniqueConstraintViolationException $e) { Mutation::reject('sku', 'SKU tidak tersedia. Gunakan SKU lain.'); }
 return to_route('products.variants.show', [$product, $variant])->with('success', 'Varian berhasil disimpan.');
 }
 public function show(Request $request, Product $product, ProductVariant $variant): View
 {
 Gate::authorize('view', $variant); Gate::authorize('view', $product);
 return view('product-variants.show', compact('product', 'variant') + ['item' => Access::items($request->user(), 'products')->withTrashed()->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $product->brand_id))->find($variant->inventory_item_id), 'inUse' => Mutation::variantUsed($variant)]);
 }
 public function edit(Request $request, Product $product, ProductVariant $variant): View { Gate::authorize('view', $product); Gate::authorize('update', $variant); return $this->form($request, $product, $variant, 'edit'); }
 private function form(Request $request, Product $product, ProductVariant $variant, string $page): View
 {
 return view('product-variants.'.$page, compact('product', 'variant') + ['items' => Access::items($request->user(), 'products')->withTrashed()->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $product->brand_id))->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))->orWhere('id', $variant->inventory_item_id ?? 0))->orderBy('name')->get(), 'inUse' => $variant->exists && Mutation::variantUsed($variant)]);
 }
 public function destroy(Request $request, Product $product, ProductVariant $variant): RedirectResponse { return $this->archive($request, $product, $variant, false); }
 public function restore(Request $request, Product $product, ProductVariant $variant): RedirectResponse { return $this->archive($request, $product, $variant, true); }
 private function archive(Request $request, Product $product, ProductVariant $variant, bool $restore): RedirectResponse
 {
 Gate::authorize('view', $product); Gate::authorize($restore ? 'restore' : 'delete', $variant);
 DB::transaction(function () use ($request, $product, $variant, $restore) {
 Mutation::lock($request); Access::products($request->user())->lockForUpdate()->findOrFail($product->id);
 $variant = Access::variants($request->user())->withTrashed()->where('product_id', $product->id)->lockForUpdate()->findOrFail($variant->id); Gate::authorize($restore ? 'restore' : 'delete', $variant);
 if (! $restore && Mutation::variantUsed($variant)) { Mutation::reject('variant', 'Varian sudah digunakan. Nonaktifkan varian melalui edit.'); }
 $before = $variant->toArray(); $variant->is_active = false;
 if ($restore) { $variant->restore(); } else { $variant->save(); $variant->delete(); }
 Mutation::audit($request, $variant, 'product_variants.'.($restore ? 'restore' : 'archive'), $before);
 }, 3);
 return to_route('products.variants.index', $product)->with('success', $restore ? 'Varian dipulihkan sebagai nonaktif.' : 'Varian diarsipkan.');
 }
}
