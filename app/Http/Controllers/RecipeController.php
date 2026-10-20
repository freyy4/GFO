<?php
namespace App\Http\Controllers;
use App\Http\Requests\{ProductRecipeIndexRequest, SaveRecipeRequest};
use App\Models\Recipe;
use App\Support\{CatalogAccess, ProductRecipeAccess as Access, ProductRecipeMutation as Mutation};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;
class RecipeController extends Controller
{
 public function index(ProductRecipeIndexRequest $request): View
 {
 $filters = $request->validated();
 $recipes = Access::recipes($request->user())->withTrashed()->with(['brand' => fn ($q) => $q->withTrashed()])
 ->when(($filters['search'] ?? '') !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')))
 ->when($filters['brand_id'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
 ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'ARCHIVED' ? $q->onlyTrashed() : $q->whereNull('deleted_at')->where('status', $s))
 ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
 return view('recipes.index', compact('recipes', 'filters') + ['brands' => CatalogAccess::brands($request->user(), 'recipes')->withTrashed()->orderBy('name')->get(), 'abilities' => CatalogAccess::abilities($request->user(), 'recipes')]);
 }
 public function create(Request $request): View { Gate::authorize('create', Recipe::class); return $this->form($request, new Recipe(['status' => 'ACTIVE']), 'create'); }
 public function store(SaveRecipeRequest $request): RedirectResponse { return $this->save($request, new Recipe); }
 public function update(SaveRecipeRequest $request, Recipe $recipe): RedirectResponse { return $this->save($request, $recipe); }
 private function save(SaveRecipeRequest $request, Recipe $recipe): RedirectResponse
 {
 try {
 $recipe = DB::transaction(function () use ($request, $recipe) {
 Mutation::lock($request);
 if ($recipe->exists) { $recipe = Access::recipes($request->user())->lockForUpdate()->findOrFail($recipe->id); }
 Gate::authorize($recipe->exists ? 'update' : 'create', $recipe->exists ? $recipe : Recipe::class);
 $data = $request->validated();
 $brand = CatalogAccess::brands($request->user(), 'recipes')->withTrashed()->lockForUpdate()->find($data['brand_id']);
 if (! $brand || ($brand->trashed() && (! $recipe->exists || $recipe->brand_id !== $brand->id))) { Mutation::reject('brand_id', 'Brand tidak tersedia.'); }
 if ($recipe->exists && (int) $data['brand_id'] !== $recipe->brand_id) { Mutation::reject('brand_id', 'Brand recipe tidak dapat dipindahkan.'); }
 if (empty($data['product_variant_id']) && empty($data['output_item_id'])) { Mutation::reject('output_item_id', 'Pilih varian produk atau inventory item sebagai output recipe.'); }
 if ($recipe->exists && $recipe->recipeVersions()->exists()) {
 foreach (['product_variant_id', 'output_item_id'] as $field) { if ((string) ($data[$field] ?? '') !== (string) ($recipe->$field ?? '')) { Mutation::reject($field, 'Output recipe yang memiliki versi tidak dapat diubah.'); } }
 }
 if (! empty($data['product_variant_id'])) {
 $variant = Access::variants($request->user(), 'recipes')->withTrashed()->whereHas('product', fn ($q) => $q->where('brand_id', $brand->id))->lockForUpdate()->find($data['product_variant_id']);
 if (! $variant || (($variant->trashed() || ! $variant->is_active) && $recipe->product_variant_id !== $variant->id)) { Mutation::reject('product_variant_id', 'Varian harus aktif dan berada pada brand recipe.'); }
 }
 if (! empty($data['output_item_id'])) {
 $item = Access::items($request->user(), 'recipes')->withTrashed()->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $brand->id))->lockForUpdate()->find($data['output_item_id']);
 if (! $item || (($item->trashed() || ! $item->is_active) && $recipe->output_item_id !== $item->id)) { Mutation::reject('output_item_id', 'Output item harus aktif dan sesuai brand atau item bersama.'); }
 if (isset($variant) && $variant->inventory_item_id && $variant->inventory_item_id !== $item->id) { Mutation::reject('output_item_id', 'Output item harus sesuai inventory item varian yang dipilih.'); }
 }
 if (Recipe::withTrashed()->where('brand_id', $brand->id)->whereRaw('LOWER(code) = ?', [strtolower($data['code'])])->when($recipe->exists, fn ($q) => $q->whereKeyNot($recipe->id))->exists()) { Mutation::reject('code', 'Kode recipe sudah digunakan pada brand ini, termasuk arsip.'); }
 $before = $recipe->toArray(); $event = $recipe->exists ? 'update' : 'create'; $recipe->fill($data)->save(); Mutation::audit($request, $recipe, 'recipes.'.$event, $before); return $recipe;
 }, 3);
 } catch (UniqueConstraintViolationException $e) { Mutation::reject('code', 'Kode recipe sudah digunakan pada brand ini.'); }
 return to_route('recipes.show', $recipe)->with('success', 'Recipe berhasil disimpan.');
 }
 public function show(Request $request, Recipe $recipe): View
 {
 Gate::authorize('view', $recipe);
 return view('recipes.show', compact('recipe') + ['brand' => CatalogAccess::brands($request->user(), 'recipes')->withTrashed()->findOrFail($recipe->brand_id),
 'variant' => Access::variants($request->user(), 'recipes')->withTrashed()->whereHas('product', fn ($q) => $q->withTrashed()->where('brand_id', $recipe->brand_id))->find($recipe->product_variant_id),
 'outputItem' => Access::items($request->user(), 'recipes')->withTrashed()->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $recipe->brand_id))->find($recipe->output_item_id), 'versionCount' => $recipe->recipeVersions()->count()]);
 }
 public function edit(Request $request, Recipe $recipe): View { Gate::authorize('update', $recipe); return $this->form($request, $recipe, 'edit'); }
 private function form(Request $request, Recipe $recipe, string $page): View
 {
 return view('recipes.'.$page, compact('recipe') + ['brands' => CatalogAccess::brands($request->user(), 'recipes')->withTrashed()->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('id', $recipe->brand_id ?? 0))->orderBy('name')->get(),
 'variants' => Access::variants($request->user(), 'recipes')->withTrashed()->whereHas('product')->with('product')->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))->orWhere('id', $recipe->product_variant_id ?? 0))->orderBy('name')->get(),
 'items' => Access::items($request->user(), 'recipes')->withTrashed()->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))->orWhere('id', $recipe->output_item_id ?? 0))->orderBy('name')->get(), 'hasVersions' => $recipe->exists && $recipe->recipeVersions()->exists()]);
 }
 public function destroy(Request $request, Recipe $recipe): RedirectResponse { return $this->archive($request, $recipe, false); }
 public function restore(Request $request, Recipe $recipe): RedirectResponse { return $this->archive($request, $recipe, true); }
 private function archive(Request $request, Recipe $recipe, bool $restore): RedirectResponse
 {
 Gate::authorize($restore ? 'restore' : 'delete', $recipe);
 DB::transaction(function () use ($request, $recipe, $restore) {
 Mutation::lock($request); $recipe = Access::recipes($request->user())->withTrashed()->lockForUpdate()->findOrFail($recipe->id); Gate::authorize($restore ? 'restore' : 'delete', $recipe);
 if (! $restore && $recipe->recipeVersions()->exists()) { Mutation::reject('recipe', 'Recipe memiliki versi. Nonaktifkan recipe untuk mempertahankan riwayat.'); }
 $before = $recipe->toArray(); $recipe->status = 'INACTIVE';
 if ($restore) { $recipe->restore(); } else { $recipe->save(); $recipe->delete(); }
 Mutation::audit($request, $recipe, 'recipes.'.($restore ? 'restore' : 'archive'), $before);
 }, 3);
 return to_route('recipes.index')->with('success', $restore ? 'Recipe dipulihkan sebagai nonaktif.' : 'Recipe diarsipkan.');
 }
}
