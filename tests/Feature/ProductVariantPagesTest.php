<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantPagesTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    private $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $org = Organization::create(['code' => 'GFO', 'name' => 'Garuta']);
        $this->user = User::factory()->create(['organization_id' => $org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $org->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'scope_type' => 'ORGANIZATION', 'is_system' => true]);
        $this->user->roles()->attach($role);
        $this->brand = Brand::create(['organization_id' => $org->id, 'code' => 'BR', 'name' => 'Brand', 'status' => 'ACTIVE']);
        $this->actingAs($this->user);
    }

    private function productData(): array
    {
        return ['brand_id' => $this->brand->id, 'code' => 'PROD', 'name' => 'Produk satu', 'description' => 'Deskripsi produk', 'is_active' => 1];
    }

    private function variantData(): array
    {
        return ['sku' => 'SKU-1', 'name' => 'Varian satu', 'selling_price' => '12500.50', 'minimum_margin_percent' => '20', 'is_active' => 1, 'attributes_json' => '{"Ukuran":"Besar"}'];
    }

    public function test_product_and_variant_save_redirects_render_details_and_edit_pages(): void
    {
        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.create'))->assertOk();
        $this->post(route('products.store'), $this->productData())->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        $this->get(route('products.show', $product))->assertOk()->assertSee('Belum ada varian')->assertSee('Tambah varian');
        $this->get(route('products.edit', $product))->assertOk();
        $this->put(route('products.update', $product), $this->productData())->assertRedirect(route('products.show', $product))->assertSessionHasNoErrors();
        $this->get(route('products.variants.create', $product))->assertOk();
        $this->post(route('products.variants.store', $product), $this->variantData())->assertSessionHasNoErrors()->assertRedirect();
        $variant = ProductVariant::firstOrFail();
        $this->get(route('products.variants.show', [$product, $variant]))->assertOk()->assertSee('12500.50')->assertSee('Besar');
        $this->get(route('products.variants.index', $product))->assertOk()->assertSee('Varian satu');
        $this->get(route('products.variants.edit', [$product, $variant]))->assertOk();
        $this->put(route('products.variants.update', [$product, $variant]), $this->variantData())->assertRedirect(route('products.variants.show', [$product, $variant]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['event' => 'products.create']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'product_variants.update']);
    }

    public function test_archived_details_render_restore_actions(): void
    {
        $product = Product::create($this->productData());
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU', 'name' => 'Variant', 'selling_price' => '1']);
        $this->delete(route('products.variants.destroy', [$product, $variant]))->assertSessionHasNoErrors();
        $this->get(route('products.variants.show', [$product, $variant]))->assertOk()->assertSee('Pulihkan');
        $this->patch(route('products.variants.restore', [$product, $variant]))->assertSessionHasNoErrors();
        $empty = Product::create(array_replace($this->productData(), ['code' => 'EMPTY']));
        $this->delete(route('products.destroy', $empty))->assertSessionHasNoErrors();
        $this->get(route('products.show', $empty))->assertOk()->assertSee('Pulihkan');
        $this->patch(route('products.restore', $empty))->assertSessionHasNoErrors();
    }

    public function test_detail_escapes_content_and_enforces_nested_tenant_binding(): void
    {
        $product = Product::create(array_replace($this->productData(), ['name' => '<script>bad()</script>']));
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU', 'name' => '<script>bad()</script>', 'selling_price' => '1', 'attributes' => ['Test' => '<script>bad()</script>']]);
        $this->get(route('products.show', $product))->assertOk()->assertDontSee('<script>bad()</script>', false);
        $this->get(route('products.variants.show', [$product, $variant]))->assertOk()->assertDontSee('<script>bad()</script>', false);
        $other = Product::create(array_replace($this->productData(), ['code' => 'OTHER']));
        $this->get(route('products.variants.show', [$other, $variant]))->assertNotFound();
        $org = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $this->brand->update(['organization_id' => $org->id]);
        $this->get(route('products.show', $product))->assertNotFound();
        $this->get(route('products.variants.show',[$product, $variant]))->assertNotFound();
    }
}
