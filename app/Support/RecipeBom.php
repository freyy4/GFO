<?php
namespace App\Support;
use App\Models\{Recipe, RecipeVersion, UnitOfMeasure, User};
use Illuminate\Validation\ValidationException;
class RecipeBom
{
 public static function validate(User $user, Recipe $recipe, array $data): void
 {
 $brand = CatalogAccess::brands($user, 'recipes')->whereKey($recipe->brand_id)->lockForUpdate()->first();
 if (! $brand || $recipe->status !== 'ACTIVE') { ProductRecipeMutation::reject('recipe', 'Recipe dan brand harus tersedia; recipe harus aktif untuk mengelola BOM.'); }
 if (! UnitOfMeasure::lockForUpdate()->find($data['yield_uom_id'])) { ProductRecipeMutation::reject('yield_uom_id', 'Satuan hasil tidak tersedia.'); }
 $outputId = $recipe->output_item_id;
 if ($recipe->product_variant_id) {
 $variant = ProductRecipeAccess::variants($user, 'recipes')->where('is_active', true)->whereHas('product', fn ($q) => $q->where('brand_id', $recipe->brand_id)->where('is_active', true))->lockForUpdate()->find($recipe->product_variant_id);
 if (! $variant) { ProductRecipeMutation::reject('recipe', 'Varian output atau produknya tidak aktif atau tidak tersedia.'); }
 if ($outputId && $variant->inventory_item_id && $outputId !== $variant->inventory_item_id) { ProductRecipeMutation::reject('recipe', 'Inventory item output tidak sesuai dengan varian.'); }
 $outputId ??= $variant->inventory_item_id;
 }
 if (! $recipe->product_variant_id && ! $outputId) { ProductRecipeMutation::reject('recipe', 'Recipe memerlukan output.'); }
 if ($outputId) {
 $output = ProductRecipeAccess::items($user, 'recipes')->where('is_active', true)->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $recipe->brand_id))->lockForUpdate()->find($outputId);
 if (! $output || (int) $data['yield_uom_id'] !== $output->base_uom_id) { ProductRecipeMutation::reject('yield_uom_id', 'Gunakan satuan dasar output item yang aktif.'); }
 }
 $seen = [];
 foreach ($data['lines'] as $index => $line) {
 $field = 'lines.'.$index;
 $itemId = $line['inventory_item_id'] ?? null; $subId = $line['sub_recipe_version_id'] ?? null;
 if ((! $itemId && ! $subId) || ($itemId && $subId)) { ProductRecipeMutation::reject($field.'.inventory_item_id', 'Pilih tepat satu bahan atau sub-recipe untuk setiap baris.'); }
 $key = $itemId ? 'item:'.$itemId : 'version:'.$subId;
 if (isset($seen[$key])) { ProductRecipeMutation::reject($field.'.quantity', 'Bahan atau sub-recipe yang sama tidak boleh diulang. Gabungkan kuantitasnya.'); }
 $seen[$key] = true;
 if ($itemId) {
 $item = ProductRecipeAccess::items($user, 'recipes')->where('is_active', true)->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $recipe->brand_id))->lockForUpdate()->find($itemId);
 if (! $item || ($outputId && $item->id === $outputId)) { ProductRecipeMutation::reject($field.'.inventory_item_id', 'Bahan tidak tersedia, tidak sesuai brand, atau sama dengan output recipe.'); }
 if ((int) $line['uom_id'] !== $item->base_uom_id) { ProductRecipeMutation::reject($field.'.uom_id', 'Satuan bahan harus sama dengan satuan dasar inventory item.'); }
 } else {
 $sub = ProductRecipeAccess::versions($user)->where('status', 'APPROVED')->whereHas('recipe', fn ($q) => $q->where('brand_id', $recipe->brand_id)->where('status', 'ACTIVE'))->lockForUpdate()->find($subId);
 if (! $sub) { ProductRecipeMutation::reject($field.'.sub_recipe_version_id', 'Sub-recipe harus approved, aktif, dan berasal dari brand yang sama.'); }
 if ((int) $line['uom_id'] !== $sub->yield_uom_id) { ProductRecipeMutation::reject($field.'.uom_id', 'Satuan sub-recipe harus sama dengan satuan hasilnya.'); }
 if ($sub->effective_from->format('Y-m-d') > $data['effective_from'] || ($sub->effective_until && (empty($data['effective_until']) || $sub->effective_until->format('Y-m-d') < $data['effective_until']))) { ProductRecipeMutation::reject($field.'.sub_recipe_version_id', 'Periode sub-recipe harus mencakup seluruh periode versi ini.'); }
 self::validateTree($user, $recipe, $sub, [], $field.'.sub_recipe_version_id', $outputId);
 }
 }
 }
 private static function validateTree(User $user, Recipe $root, RecipeVersion $version, array $path, string $field, ?int $outputId): void
 {
 if ($version->recipe_id === $root->id || in_array($version->recipe_id, $path, true) || count($path) >= 30) { ProductRecipeMutation::reject($field, 'BOM membentuk siklus atau melebihi kedalaman 30 recipe.'); }
 $path[] = $version->recipe_id;
 foreach ($version->recipeLinesAsRecipeVersion()->get() as $line) {
 if ($line->inventory_item_id) {
 $item = ProductRecipeAccess::items($user, 'recipes')->where('is_active', true)->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $root->brand_id))->find($line->inventory_item_id);
 if (! $item || ($outputId && $item->id === $outputId) || $line->sub_recipe_version_id) { ProductRecipeMutation::reject($field, 'Sub-recipe memiliki bahan tidak valid atau memakai output recipe induk.'); }
 } elseif ($line->sub_recipe_version_id) {
 $sub = ProductRecipeAccess::versions($user)->where('status', 'APPROVED')->whereHas('recipe', fn ($q) => $q->where('brand_id', $root->brand_id)->where('status', 'ACTIVE'))->find($line->sub_recipe_version_id);
 if (! $sub) { ProductRecipeMutation::reject($field, 'Rantai sub-recipe tidak tersedia dalam cakupan akses.'); }
 self::validateTree($user, $root, $sub, $path, $field, $outputId);
 } else { ProductRecipeMutation::reject($field, 'Sub-recipe memiliki baris tanpa bahan.'); }
 }
 }
}
