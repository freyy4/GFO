<?php
namespace App\Http\Controllers;
use App\Http\Requests\{ProductRecipeIndexRequest, SaveProductRequest};
use App\Models\{Product, ProductCategory};
use App\Support\{CatalogAccess, ProductRecipeAccess as Access, ProductRecipeMutation as Mutation};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;

class ProductController extends Controller
{
 public function index(ProductRecipeIndexRequest $request): View
 {
 $filters = $request->validated();
 $products = Access::products($request->user())->withTrashed()->with(['brand' => fn ($q) => $q->withTrashed()])
 ->when(($filters['search'] ?? '') !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')))
 ->when($filters['brand_id'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
 ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'ARCHIVED' ? $q->onlyTrashed() : $q->whereNull('deleted_at')->where('is_active', $s === 'ACTIVE'))
 ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
 return view('products.index', ['products' => $products, 'filters' => $filters, 'brands' => CatalogAccess::brands($request->user(), 'products')->withTrashed()->orderBy('name')->get(), 'abilities' => CatalogAccess::abilities($request->user(), 'products')]);
 }
 public function create(Request $request): View { Gate::authorize('create', Product::class); return $this->form($request, new Product(['is_active' => true]), 'create'); }
 public function store(SaveProductRequest $request): RedirectResponse { return $this->save($request, new Product); }
 public function update(SaveProductRequest $request, Product $product): RedirectResponse { return $this->save($request, $product); }
 private function save(SaveProductRequest $request, Product $product): RedirectResponse
 {
 try {
 $product = DB::transaction(function () use ($request, $product) {
 Mutation::lock($request);
 if ($product->exists) { $product = Access::products($request->user())->lockForUpdate()->findOrFail($product->id); }
 Gate::authorize($product->exists ? 'update' : 'create', $product->exists ? $product : Product::class);
 $data = $request->validated();
 $brand = CatalogAccess::brands($request->user(), 'products')->withTrashed()->lockForUpdate()->find($data['brand_id']);
 if (! $brand || ($brand->trashed() && (! $product->exists || $product->brand_id !== $brand->id))) { Mutation::reject('brand_id', 'Brand tidak tersedia.'); }
 if ($product->exists && (int) $data['brand_id'] !== $product->brand_id) { Mutation::reject('brand_id', 'Brand produk tidak dapat dipindahkan.'); }
 if (! empty($data['product_category_id'])) {
 $category = ProductCategory::where('brand_id', $brand->id)->lockForUpdate()->find($data['product_category_id']);
 if (! $category || (! $category->is_active && $product->product_category_id !== $category->id)) { Mutation::reject('product_category_id', 'Kategori harus aktif dan berada pada brand produk.'); }
 }
 if (Product::withTrashed()->where('brand_id', $brand->id)->whereRaw('LOWER(code) = ?', [strtolower($data['code'])])->when($product->exists, fn ($q) => $q->whereKeyNot($product->id))->exists()) { Mutation::reject('code', 'Kode sudah digunakan dalam brand ini, termasuk arsip.'); }
 $before = $product->toArray(); $event = $product->exists ? 'update' : 'create';
 $product->fill($data)->save(); Mutation::audit($request, $product, 'products.'.$event, $before); return $product;
 }, 3);
 } catch (UniqueConstraintViolationException $e) { Mutation::reject('code', 'Kode sudah digunakan dalam brand ini.'); }
 return to_route('products.show', $product)->with('success', 'Produk berhasil disimpan.');
 }
 public function show(Request $request, Product $product): View
 {
 Gate::authorize('view', $product);
 return view('products.show', ['product' => $product, 'brand' => CatalogAccess::brands($request->user(), 'products')->withTrashed()->findOrFail($product->brand_id), 'category' => ProductCategory::where('brand_id', $product->brand_id)->find($product->product_category_id), 'variantCount' => $product->productVariants()->withTrashed()->count()]);
 }
 public function edit(Request $request, Product $product): View { Gate::authorize('update', $product); return $this->form($request, $product, 'edit'); }
 private function form(Request $request, Product $product, string $page): View
 {
 return view('products.'.$page, ['product' => $product, 'brands' => CatalogAccess::brands($request->user(), 'products')->withTrashed()->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('id', $product->brand_id ?? 0))->orderBy('name')->get(),
 'categories' => ProductCategory::whereIn('brand_id', CatalogAccess::brands($request->user(), 'products')->withTrashed()->select('id'))->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product->product_category_id ?? 0))->orderBy('name')->get()]);
 }
 public function destroy(Request $request, Product $product): RedirectResponse { return $this->archive($request, $product, false); }
 public function restore(Request $request, Product $product): RedirectResponse { return $this->archive($request, $product, true); }
 private function archive(Request $request, Product $product, bool $restore): RedirectResponse
 {
 Gate::authorize($restore ? 'restore' : 'delete', $product);
 DB::transaction(function () use ($request, $product, $restore) {
 Mutation::lock($request); $product = Access::products($request->user())->withTrashed()->lockForUpdate()->findOrFail($product->id);
 Gate::authorize($restore ? 'restore' : 'delete', $product);
 if (! $restore && $product->productVariants()->withTrashed()->exists()) { Mutation::reject('product', 'Produk memiliki varian. Nonaktifkan produk untuk mempertahankan riwayat.'); }
 $before = $product->toArray(); $product->is_active = false;
 if ($restore) { $product->restore(); } else { $product->save(); $product->delete(); }
 Mutation::audit($request, $product, 'products.'.($restore ? 'restore' : 'archive'), $before);
 }, 3);
 return to_route('products.index')->with('success', $restore ? 'Produk dipulihkan sebagai nonaktif.' : 'Produk diarsipkan.');
 }
}
