<?php
namespace App\Http\Controllers;
use App\Http\Requests\{ProductRecipeIndexRequest, SaveRecipeVersionRequest};
use App\Models\{Recipe, RecipeVersion, UnitOfMeasure};
use App\Support\{CatalogAccess, ProductRecipeAccess as Access, ProductRecipeMutation as Mutation, RecipeBom};
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Support\Facades\{DB, Gate, Validator};
use Illuminate\View\View;
class RecipeVersionController extends Controller
{
 public function index(ProductRecipeIndexRequest $request, Recipe $recipe): View
 {
 Gate::authorize('view', $recipe); $filters = $request->validated();
 $versions = Access::versions($request->user())->where('recipe_id', $recipe->id)->with('yieldUom')
 ->when(($filters['search'] ?? '') !== '', fn ($q) => $q->where(fn ($q) => $q->where('version_no', 'like', '%'.$filters['search'].'%')->orWhere('notes', 'like', '%'.$filters['search'].'%')))
 ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->orderByDesc('version_no')->paginate(15)->withQueryString();
 return view('recipe-versions.index', compact('recipe', 'versions', 'filters') + ['abilities' => CatalogAccess::abilities($request->user(), 'recipes')]);
 }
 public function create(Request $request, Recipe $recipe): View { Gate::authorize('view', $recipe); Gate::authorize('create', RecipeVersion::class); return $this->form($request, $recipe, new RecipeVersion(['yield_quantity' => 1, 'effective_from' => today(), 'status' => 'DRAFT']), 'create'); }
 public function store(SaveRecipeVersionRequest $request, Recipe $recipe): RedirectResponse { return $this->save($request, $recipe, new RecipeVersion); }
 public function update(SaveRecipeVersionRequest $request, Recipe $recipe, RecipeVersion $version): RedirectResponse { return $this->save($request, $recipe, $version); }
 private function save(SaveRecipeVersionRequest $request, Recipe $recipe, RecipeVersion $version): RedirectResponse
 {
 $version = DB::transaction(function () use ($request, $recipe, $version) {
 Mutation::lock($request); $recipe = Access::recipes($request->user())->lockForUpdate()->findOrFail($recipe->id); Gate::authorize('view', $recipe);
 if ($version->exists) { $version = Access::versions($request->user())->where('recipe_id', $recipe->id)->lockForUpdate()->findOrFail($version->id); }
 Gate::authorize($version->exists ? 'update' : 'create', $version->exists ? $version : RecipeVersion::class);
 if ($version->exists && Mutation::versionUsed($version)) { Mutation::reject('version', 'Versi sudah direferensikan dan tidak dapat diubah. Buat versi baru.'); }
 $data = $request->validated(); RecipeBom::validate($request->user(), $recipe, $data);
 $before = $version->exists ? Mutation::snapshot($version) : []; $event = $version->exists ? 'update' : 'create';
 $lines = $data['lines']; unset($data['lines']);
 $version->fill($data);
 if (! $version->exists) { $version->recipe_id = $recipe->id; $version->version_no = ((int) $recipe->recipeVersions()->max('version_no')) + 1; $version->status = 'DRAFT'; }
 $version->save(); $version->recipeLinesAsRecipeVersion()->delete();
 foreach ($lines as $index => $line) { $version->recipeLinesAsRecipeVersion()->create(array_merge($line, ['sort_order' => $index])); }
 Mutation::audit($request, $version, 'recipe_versions.'.$event, $before, Mutation::snapshot($version)); return $version;
 }, 3);
 return to_route('recipes.versions.show', [$recipe, $version])->with('success', 'Draft versi dan BOM berhasil disimpan.');
 }
 public function show(Request $request, Recipe $recipe, RecipeVersion $version): View
 {
 Gate::authorize('view', $recipe); Gate::authorize('view', $version);
 $version->load('yieldUom');
 $lines = $version->recipeLinesAsRecipeVersion()->orderBy('sort_order')->get();
 $items = Access::items($request->user(), 'recipes')->withTrashed()->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $recipe->brand_id))->whereIn('id', $lines->pluck('inventory_item_id')->filter())->get()->keyBy('id');
 $subVersions = Access::versions($request->user())->whereHas('recipe', fn ($q) => $q->withTrashed()->where('brand_id', $recipe->brand_id))->whereIn('id', $lines->pluck('sub_recipe_version_id')->filter())->with(['recipe' => fn ($q) => $q->withTrashed()])->get()->keyBy('id');
 $uoms = UnitOfMeasure::whereIn('id', $lines->pluck('uom_id'))->get()->keyBy('id');
 return view('recipe-versions.show', compact('recipe', 'version', 'lines', 'items', 'subVersions', 'uoms'));
 }
 public function edit(Request $request, Recipe $recipe, RecipeVersion $version): View { Gate::authorize('view', $recipe); Gate::authorize('update', $version); return $this->form($request, $recipe, $version, 'edit'); }
 private function form(Request $request, Recipe $recipe, RecipeVersion $version, string $page): View
 {
 return view('recipe-versions.'.$page, compact('recipe', 'version') + ['lines' => $version->exists ? $version->recipeLinesAsRecipeVersion()->orderBy('sort_order')->get()->toArray() : [],
 'items' => Access::items($request->user(), 'recipes')->where('is_active', true)->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $recipe->brand_id))->with('baseUom')->orderBy('name')->get(),
 'subVersions' => Access::versions($request->user())->where('status', 'APPROVED')->where('recipe_id', '!=', $recipe->id)->whereHas('recipe', fn ($q) => $q->where('brand_id', $recipe->brand_id)->where('status', 'ACTIVE'))->with(['recipe', 'yieldUom'])->orderBy('recipe_id')->orderByDesc('version_no')->get(),
 'uoms' => UnitOfMeasure::orderBy('code')->get()]);
 }
 public function destroy(Request $request, Recipe $recipe, RecipeVersion $version): RedirectResponse
 {
 Gate::authorize('view', $recipe); Gate::authorize('delete', $version);
 DB::transaction(function () use ($request, $recipe, $version) {
 Mutation::lock($request); Access::recipes($request->user())->lockForUpdate()->findOrFail($recipe->id);
 $version = Access::versions($request->user())->where('recipe_id', $recipe->id)->lockForUpdate()->findOrFail($version->id); Gate::authorize('delete', $version);
 if (Mutation::versionUsed($version)) { Mutation::reject('version', 'Versi sudah digunakan dan tidak dapat dihapus.'); }
 $before = Mutation::snapshot($version); $version->delete(); Mutation::audit($request, $version, 'recipe_versions.delete', $before, []);
 }, 3);
 return to_route('recipes.versions.index', $recipe)->with('success', 'Draft versi dihapus.');
 }
 public function approve(Request $request, Recipe $recipe, RecipeVersion $version): RedirectResponse
 {
 Gate::authorize('view', $recipe); Gate::authorize('approve', $version);
 DB::transaction(function () use ($request, $recipe, $version) {
 Mutation::lock($request); $recipe = Access::recipes($request->user())->lockForUpdate()->findOrFail($recipe->id);
 $version = Access::versions($request->user())->where('recipe_id', $recipe->id)->lockForUpdate()->findOrFail($version->id); Gate::authorize('approve', $version);
 if (Mutation::versionUsed($version)) { Mutation::reject('version', 'Draft sudah direferensikan. Persetujuan ditolak untuk menjaga riwayat.'); }
 $data = $version->only(['yield_quantity', 'yield_uom_id', 'notes']);
 $data['effective_from'] = $version->effective_from->format('Y-m-d'); $data['effective_until'] = $version->effective_until?->format('Y-m-d');
 $data['lines'] = $version->recipeLinesAsRecipeVersion()->orderBy('sort_order')->get()->map(fn ($line) => $line->only(['inventory_item_id', 'sub_recipe_version_id', 'uom_id', 'quantity', 'waste_percent']))->all();
 if (! $data['effective_until']) { Mutation::reject('effective_until', 'Tentukan tanggal akhir berlaku sebelum persetujuan agar versi berikutnya dapat dijadwalkan.'); }
 Validator::make($data, (new SaveRecipeVersionRequest)->rules())->validate(); RecipeBom::validate($request->user(), $recipe, $data);
 $overlap = $recipe->recipeVersions()->where('status', 'APPROVED')->where('id', '!=', $version->id)
 ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $data['effective_from']))
 ->when($data['effective_until'], fn ($q, $until) => $q->where('effective_from', '<=', $until))->exists();
 if ($overlap) { Mutation::reject('effective_from', 'Periode berlaku bertumpang tindih dengan versi approved. Gunakan periode yang tidak beririsan.'); }
 $before = Mutation::snapshot($version); $version->update(['status' => 'APPROVED', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
 Mutation::audit($request, $version, 'recipe_versions.approve', $before, Mutation::snapshot($version));
 }, 3);
 return to_route('recipes.versions.show', [$recipe, $version])->with('success', 'Versi disetujui. BOM kini dikunci.');
 }
}
