<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\InventoryItem;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private Brand $brand;

    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->organization = Organization::create(['code' => 'GFO', 'name' => 'Garuta']);
        $this->brand = Brand::create(['organization_id' => $this->organization->id, 'code' => 'B', 'name' => 'Brand A']);
        $this->uom = UnitOfMeasure::create(['code' => 'KG', 'name' => 'Kilogram', 'decimal_places' => 4]);
        $this->admin = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->organization->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'scope_type' => 'ORGANIZATION', 'is_system' => true]);
        $this->admin->roles()->attach($role);
    }

    private function categoryData(array $overrides = []): array
    {
        return array_replace(['brand_id' => $this->brand->id, 'parent_id' => null, 'name' => 'Makanan', 'slug' => 'makanan', 'sort_order' => 0, 'is_active' => true], $overrides);
    }

    private function itemData(array $overrides = []): array
    {
        return array_replace(['brand_id' => $this->brand->id, 'base_uom_id' => $this->uom->id, 'sku' => 'TEPUNG', 'name' => 'Tepung', 'item_type' => 'RAW_MATERIAL', 'tracking_policy' => 'NONE', 'issue_policy' => 'FIFO', 'reorder_point' => '12.3456', 'safety_stock' => '1.0000', 'standard_cost' => '5000.2500', 'is_active' => true], $overrides);
    }

    private function item(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_replace($this->itemData(), ['organization_id' => $this->organization->id], $overrides));
    }

    public function test_crud_audits_and_archive_restore_lifecycle(): void
    {
        $this->actingAs($this->admin)->post(route('product-categories.store'), $this->categoryData(['slug' => '']))->assertSessionHasNoErrors()->assertRedirect();
        $category = ProductCategory::firstOrFail();
        $this->assertSame('makanan', $category->slug);
        $this->put(route('product-categories.update', $category), $this->categoryData(['name' => 'Menu', 'is_active' => false]))->assertSessionHasNoErrors();
        $this->delete(route('product-categories.destroy', $category))->assertRedirect(route('product-categories.index'));
        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
        $this->post(route('inventory-items.store'), $this->itemData(['sku' => ' tepung ']))->assertSessionHasNoErrors();
        $item = InventoryItem::firstOrFail();
        $this->assertSame('TEPUNG', $item->sku);
        $this->assertSame('12.3456', $item->reorder_point);
        $this->put(route('inventory-items.update', $item), $this->itemData(['name' => 'Tepung baru']))->assertSessionHasNoErrors();
        $this->delete(route('inventory-items.destroy', $item))->assertRedirect(route('inventory-items.index'));
        $this->assertSoftDeleted($item);
        $this->get(route('inventory-items.show', $item))->assertOk()->assertSee('Diarsipkan');
        $this->get(route('inventory-items.edit', $item))->assertNotFound();
        $this->patch(route('inventory-items.restore', $item))->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_active);
        $this->assertSame(['product_categories.create', 'product_categories.update', 'product_categories.delete', 'inventory_items.create', 'inventory_items.update', 'inventory_items.archive', 'inventory_items.restore'], AuditLog::orderBy('id')->pluck('event')->all());
        foreach (AuditLog::all() as $audit) {
            $this->assertSame($this->admin->id, $audit->user_id);
            $this->assertSame($this->organization->id, $audit->organization_id);
        }
        $this->assertSame('Tepung', AuditLog::where('event', 'inventory_items.update')->first()->old_values['name']);
    }

    public function test_cross_organization_routes_and_forged_references_are_blocked(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Secret']);
        $brand = Brand::create(['organization_id' => $other->id, 'code' => 'SECRET', 'name' => 'Secret brand']);
        $category = ProductCategory::create($this->categoryData(['brand_id' => $brand->id, 'name' => 'Secret category']));
        $item = $this->item(['organization_id' => $other->id, 'brand_id' => $brand->id, 'name' => 'Secret item']);
        $this->actingAs($this->admin);
        foreach (['product-categories' => $category, 'inventory-items' => $item] as $module => $record) {
            foreach (['show', 'edit'] as $action) {
                $this->get(route($module.'.'.$action, $record))->assertNotFound();
            }
            $this->put(route($module.'.update', $record), [])->assertNotFound();
            $this->delete(route($module.'.destroy', $record))->assertNotFound();
            foreach (['index', 'create'] as $action) {
                $this->get(route($module.'.'.$action))->assertOk()->assertDontSee('Secret');
            }
        }
        $item->delete();
        $this->patch(route('inventory-items.restore', $item))->assertNotFound();
        $this->post(route('product-categories.store'), $this->categoryData(['brand_id' => $brand->id]))->assertSessionHasErrors('brand_id');
        $this->post(route('product-categories.store'), $this->categoryData(['parent_id' => $category->id]))->assertSessionHasErrors('parent_id');
        $this->post(route('inventory-items.store'), $this->itemData(['brand_id' => $brand->id]))->assertSessionHasErrors('brand_id');
        foreach (['organization_id', 'operational_unit_id', 'deleted_at'] as $field) {
            $this->post(route('inventory-items.store'), $this->itemData([$field => null]))->assertSessionHasErrors($field);
            $this->post(route('product-categories.store'), $this->categoryData([$field => null]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unit_assignments_limit_reads_to_brand_and_shared_items_and_disallow_writes(): void
    {
        $category = ProductCategory::create($this->categoryData());
        $item = $this->item();
        $shared = $this->item(['sku' => 'SHARED', 'brand_id' => null]);
        $brand = Brand::create(['organization_id' => $this->organization->id, 'code' => 'B2', 'name' => 'Hidden brand']);
        $hiddenCategory = ProductCategory::create($this->categoryData(['brand_id' => $brand->id]));
        $hiddenItem = $this->item(['sku' => 'HIDDEN', 'brand_id' => $brand->id]);
        $unit = OperationalUnit::create(['organization_id' => $this->organization->id, 'brand_id' => $this->brand->id, 'code' => 'U', 'name' => 'Booth', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $viewer = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $viewer->roles()->attach($this->admin->roles()->first(), ['operational_unit_id' => $unit->id]);
        $this->actingAs($viewer);
        $this->get(route('inventory-items.show', $shared))->assertOk();
        foreach (['product-categories' => [$category, $hiddenCategory], 'inventory-items' => [$item, $hiddenItem]] as $module => [$visible, $hidden]) {
            $this->get(route($module.'.index'))->assertOk()->assertDontSee('Hidden brand');
            $this->get(route($module.'.show', $visible))->assertOk();
            $this->get(route($module.'.show', $hidden))->assertNotFound();
            $this->get(route($module.'.create'))->assertForbidden();
            $this->get(route($module.'.edit', $visible))->assertForbidden();
            $this->post(route($module.'.store'), [])->assertForbidden();
            $this->put(route($module.'.update', $visible), [])->assertForbidden();
            $this->delete(route($module.'.destroy', $visible))->assertForbidden();
        }
        $unit->update(['status' => 'INACTIVE']);
        $this->get(route('inventory-items.index'))->assertForbidden();
        $this->get(route('product-categories.index'))->assertForbidden();
    }

    public function test_guest_inactive_and_invalid_assignments_cannot_access_catalog(): void
    {
        foreach (['product-categories', 'inventory-items'] as $module) {
            $this->get(route($module.'.index'))->assertRedirect(route('login'));
        }
        $this->admin->roles()->updateExistingPivot($this->admin->roles()->first()->id, ['starts_at' => now()->addDay()]);
        $this->actingAs($this->admin)->get(route('inventory-items.index'))->assertForbidden();
        $this->admin->roles()->updateExistingPivot($this->admin->roles()->first()->id, ['starts_at' => null, 'ends_at' => now()->subDay()]);
        $this->get(route('product-categories.index'))->assertForbidden();
        $this->admin->roles()->updateExistingPivot($this->admin->roles()->first()->id, ['ends_at' => null]);
        $this->admin->update(['status' => 'INACTIVE']);
        $this->get(route('inventory-items.index'))->assertForbidden();
        $this->admin->update(['status' => 'ACTIVE']);
        $this->organization->update(['status' => 'INACTIVE']);
        $this->get(route('product-categories.index'))->assertForbidden();
    }

    public function test_custom_organization_permissions_authorize_only_granted_actions(): void
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->organization->id, 'code' => 'CATALOG', 'name' => 'Catalog', 'scope_type' => 'ORGANIZATION']);
        $user->roles()->attach($role);
        foreach (['product_categories', 'inventory_items'] as $module) {
            foreach (['view', 'create'] as $action) {
                $role->permissions()->attach(Permission::create(['code' => $module.'.'.$action, 'name' => $action, 'module' => $module]));
            }
        }
        $this->actingAs($user)->post(route('product-categories.store'), $this->categoryData())->assertSessionHasNoErrors();
        $this->post(route('inventory-items.store'), $this->itemData())->assertSessionHasNoErrors();
        $this->delete(route('product-categories.destroy', ProductCategory::first()))->assertForbidden();
        $this->put(route('inventory-items.update', InventoryItem::first()), $this->itemData())->assertForbidden();
    }

    public function test_category_hierarchy_duplicates_and_used_category_protection(): void
    {
        $parent = ProductCategory::create($this->categoryData());
        $child = ProductCategory::create($this->categoryData(['slug' => 'child', 'parent_id' => $parent->id]));
        $brand = Brand::create(['organization_id' => $this->organization->id, 'code' => 'B2', 'name' => 'Brand 2']);
        $foreignParent = ProductCategory::create($this->categoryData(['brand_id' => $brand->id]));
        $this->actingAs($this->admin);
        foreach ([$parent, $child, $foreignParent] as $invalidParent) {
            $this->put(route('product-categories.update', $parent), $this->categoryData(['parent_id' => $invalidParent->id]))->assertSessionHasErrors('parent_id');
        }
        $this->put(route('product-categories.update', $parent), $this->categoryData(['brand_id' => $brand->id, 'slug' => 'moved']))->assertSessionHasErrors('brand_id');
        $this->post(route('product-categories.store'), $this->categoryData(['slug' => 'MAKANAN']))->assertSessionHasErrors('slug');
        $this->delete(route('product-categories.destroy', $parent))->assertSessionHasErrors('category');
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $child->id, 'code' => 'P', 'name' => 'Product']);
        $product->delete();
        $this->delete(route('product-categories.destroy', $child))->assertSessionHasErrors('category');
        $this->put(route('product-categories.update', $parent), $this->categoryData(['is_active' => false]))->assertSessionHasNoErrors();
        $this->assertFalse($parent->fresh()->is_active);
    }

    public function test_used_inventory_identity_is_locked_but_metadata_can_be_updated(): void
    {
        $item = $this->item();
        $recipe = Recipe::create(['brand_id' => $this->brand->id, 'output_item_id' => $item->id, 'code' => 'R', 'name' => 'Recipe']);
        $recipe->delete();
        $uom = UnitOfMeasure::create(['code' => 'G', 'name' => 'Gram']);
        $this->actingAs($this->admin);
        foreach (['sku' => 'CHANGED', 'brand_id' => null, 'base_uom_id' => $uom->id, 'item_type' => 'PACKAGING', 'tracking_policy' => 'LOT'] as $field => $value) {
            $this->put(route('inventory-items.update', $item), $this->itemData([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->delete(route('inventory-items.destroy', $item))->assertSessionHasErrors('item');
        $this->put(route('inventory-items.update', $item), $this->itemData(['name' => 'Updated', 'is_active' => false]))->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_active);
        $this->assertSame('Updated', $item->fresh()->name);
        $this->get(route('inventory-items.edit', $item))->assertOk()->assertSee('disabled', false);
    }

    public function test_inventory_validation_and_archived_sku_reservation(): void
    {
        $item = $this->item();
        $item->delete();
        $this->actingAs($this->admin)->post(route('inventory-items.store'), $this->itemData(['sku' => 'tepung']))->assertSessionHasErrors('sku');
        $this->post(route('inventory-items.store'), $this->itemData(['sku' => [], 'base_uom_id' => 99999, 'reorder_point' => -1, 'safety_stock' => '1.12345', 'standard_cost' => '100000000000000', 'item_type' => 'BAD', 'issue_policy' => 'FEFO']))->assertSessionHasErrors(['sku', 'base_uom_id', 'reorder_point', 'safety_stock', 'standard_cost', 'item_type', 'issue_policy']);
        $this->post(route('inventory-items.store'), $this->itemData(['sku' => 'NEW', 'tracking_policy' => 'LOT_EXPIRY', 'issue_policy' => 'FEFO']))->assertSessionHasNoErrors();
    }

    public function test_search_filters_pagination_and_all_pages_escape_content(): void
    {
        for ($i = 0; $i < 17; $i++) {
            ProductCategory::create($this->categoryData(['slug' => 'category-'.$i, 'name' => 'Category '.$i]));
            $this->item(['sku' => 'SKU-'.$i, 'name' => 'Item '.$i]);
        }
        $category = ProductCategory::create($this->categoryData(['name' => '<script>alert(1)</script>', 'is_active' => false]));
        $item = $this->item(['name' => '<script>alert(1)</script>', 'is_active' => false]);
        $this->actingAs($this->admin);
        foreach (['product-categories' => [$category, 'categories'], 'inventory-items' => [$item, 'items']] as $module => [$record, $variable]) {
            foreach (['index', 'create', 'show', 'edit'] as $action) {
                $this->get(route($module.'.'.$action, in_array($action, ['show', 'edit']) ? $record : []))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('Sidebar admin');
            }
            $this->get(route($module.'.index'))->assertViewHas($variable, fn ($rows) => $rows->total() === 18 && $rows->count() === 15);
            $this->get(route($module.'.index', ['search' => 'alert', 'status' => 'INACTIVE', 'brand_id' => $this->brand->id]))->assertViewHas($variable, fn ($rows) => $rows->total() === 1);
            $this->get(route($module.'.index', ['search' => 'absent']))->assertSee('Belum ada');
            $this->get(route($module.'.index', ['page' => 0, 'status' => 'BAD']))->assertSessionHasErrors(['page', 'status']);
        }
        $item->delete();
        $this->get(route('inventory-items.index', ['status' => 'ARCHIVED', 'item_type' => 'RAW_MATERIAL']))->assertViewHas('items', fn ($rows) => $rows->total() === 1);
        $this->from(route('product-categories.edit', $category))->put(route('product-categories.update', $category), $this->categoryData(['name' => 'Retained', 'sort_order' => -1]))->assertRedirect();
        $this->get(route('product-categories.edit', $category))->assertSee('Retained')->assertSee('aria-invalid="true"', false);
    }

    public function test_failed_audit_rolls_back_every_mutation(): void
    {
        $category = ProductCategory::create($this->categoryData());
        $item = $this->item();
        $archived = $this->item(['sku' => 'ARCHIVED']);
        $archived->delete();
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->actingAs($this->admin)->post(route('product-categories.store'), $this->categoryData(['slug' => 'new']))->assertStatus(500);
            $this->put(route('product-categories.update', $category), $this->categoryData(['name' => 'Changed']))->assertStatus(500);
            $this->delete(route('product-categories.destroy', $category))->assertStatus(500);
            $this->assertSame('Makanan', $category->fresh()->name);
            $this->assertDatabaseCount('product_categories', 1);
            $this->post(route('inventory-items.store'), $this->itemData(['sku' => 'NEW']))->assertStatus(500);
            $this->put(route('inventory-items.update', $item), $this->itemData(['name' => 'Changed']))->assertStatus(500);
            $this->delete(route('inventory-items.destroy', $item))->assertStatus(500);
            $this->patch(route('inventory-items.restore', $archived))->assertStatus(500);
            $this->assertSame('Tepung', $item->fresh()->name);
            $this->assertNotSoftDeleted($item);
            $this->assertSoftDeleted($archived);
            $this->assertDatabaseCount('inventory_items', 2);
        } finally {
            AuditLog::flushEventListeners();
        }
    }
}
