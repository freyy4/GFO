<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->organization = Organization::create(['code' => 'GARUTA', 'name' => 'Garuta']);
        $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['code' => 'BRAND-01', 'name' => 'Garuta Brand', 'business_model' => 'FNB_BOOTH',
            'inventory_policy' => 'FEFO', 'costing_method' => 'RECIPE_PURCHASE_COST', 'settings' => '{"note":"test"}'], $overrides);
    }

    private function brand(array $overrides = []): Brand
    {
        return Brand::create(array_replace($this->data(['settings' => ['note' => 'test']]),
            ['organization_id' => $this->organization->id, 'status' => 'DRAFT'], $overrides));
    }

    public function test_guests_and_users_without_valid_organization_are_denied(): void
    {
        $this->get(route('brands.index'))->assertRedirect(route('login'));
        $this->post(route('brands.store'), $this->data())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('brands.index'))->assertForbidden();
        $this->user->update(['status' => 'INACTIVE']);
        $this->actingAs($this->user)->get(route('brands.index'))->assertForbidden();
    }

    public function test_full_lifecycle_preserves_activation_and_records_each_event(): void
    {
        $this->actingAs($this->user)->post(route('brands.store'), $this->data(['organization_id' => 999999]))->assertRedirect();
        $brand = Brand::firstOrFail();
        $this->assertSame($this->organization->id, $brand->organization_id);
        $this->assertSame('DRAFT', $brand->status);
        $this->assertSame(['note' => 'test'], $brand->settings);
        $this->put(route('brands.update', $brand), $this->data(['name' => 'Updated Brand', 'organization_id' => 999999]))->assertRedirect();
        $this->patch(route('brands.activate', $brand))->assertRedirect();
        $activatedAt = $brand->fresh()->activated_at;
        $this->assertNotNull($activatedAt);
        $this->assertSame('ACTIVE', $brand->fresh()->status);
        $this->patch(route('brands.deactivate', $brand))->assertRedirect();
        $this->assertSame('INACTIVE', $brand->fresh()->status);
        $this->assertTrue($activatedAt->equalTo($brand->fresh()->activated_at));
        $this->delete(route('brands.destroy', $brand))->assertRedirect();
        $this->assertSoftDeleted($brand);
        $this->assertSame('ARCHIVED', $brand->fresh()->status);
        $this->get(route('brands.index'))->assertOk()->assertSee('Pulihkan');
        $this->patch(route('brands.restore', $brand))->assertRedirect();
        $this->assertNull($brand->fresh()->deleted_at);
        $this->assertSame('INACTIVE', $brand->fresh()->status);
        $this->assertSame(['create', 'update', 'activate', 'deactivate', 'delete', 'restore'], AuditLog::orderBy('id')->pluck('event')->all());
        $log = AuditLog::where('event', 'update')->firstOrFail();
        $this->assertSame('Garuta Brand', $log->old_values['name']);
        $this->assertSame('Updated Brand', $log->new_values['name']);
        $this->assertSame($this->user->id, $log->user_id);
    }

    public function test_every_record_route_hides_other_organizations_including_restore(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $brand = $this->brand(['organization_id' => $other->id, 'name' => 'Secret Brand']);
        $this->actingAs($this->user);
        foreach (['show', 'edit'] as $action) {
            $this->get(route('brands.'.$action, $brand))->assertNotFound();
        }
        $this->put(route('brands.update', $brand), $this->data())->assertNotFound();
        foreach (['activate', 'deactivate', 'restore'] as $action) {
            $this->patch(route('brands.'.$action, $brand))->assertNotFound();
        }
        $this->delete(route('brands.destroy', $brand))->assertNotFound();
        $brand->delete();
        $this->patch(route('brands.restore', $brand))->assertNotFound();
        $this->get(route('brands.index'))->assertOk()->assertDontSee('Secret Brand');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_rejects_invalid_data_and_direct_status_changes(): void
    {
        $this->actingAs($this->user)->post(route('brands.store'), $this->data([
            'code' => 'lower case', 'name' => '', 'business_model' => '', 'inventory_policy' => 'LIFO',
            'costing_method' => '', 'settings' => '{broken', 'status' => 'ACTIVE', 'activated_at' => now()->toISOString(),
        ]))->assertSessionHasErrors(['code', 'name', 'business_model', 'inventory_policy', 'costing_method', 'settings', 'status', 'activated_at']);
        $brand = $this->brand();
        $this->put(route('brands.update', $brand), $this->data(['status' => 'ACTIVE']))->assertSessionHasErrors('status');
        $this->put(route('brands.update', $brand), $this->data(['status' => null, 'activated_at' => null, 'deleted_at' => null]))
            ->assertSessionHasErrors(['status', 'activated_at', 'deleted_at']);
        $this->assertSame('DRAFT', $brand->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_codes_are_unique_per_organization_even_when_deleted(): void
    {
        $brand = $this->brand();
        $this->actingAs($this->user)->post(route('brands.store'), $this->data())->assertSessionHasErrors('code');
        $this->put(route('brands.update', $brand), $this->data())->assertSessionHasNoErrors();
        $brand->delete();
        $this->post(route('brands.store'), $this->data())->assertSessionHasErrors('code');
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $this->user->update(['organization_id' => $other->id]);
        $this->post(route('brands.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('brands', 2);
    }

    public function test_activation_requires_complete_data_and_valid_transitions(): void
    {
        $brand = $this->brand(['costing_method' => '']);
        $this->actingAs($this->user)->patch(route('brands.activate', $brand))->assertSessionHasErrors('costing_method');
        $this->assertSame('DRAFT', $brand->fresh()->status);
        $this->patch(route('brands.deactivate', $brand))->assertForbidden();
        $this->patch(route('brands.restore', $brand))->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
        $brand->update(['costing_method' => 'RECIPE_PURCHASE_COST']);
        $this->patch(route('brands.activate', $brand))->assertRedirect();
        $this->patch(route('brands.activate', $brand))->assertForbidden();
        $this->delete(route('brands.destroy', $brand))->assertRedirect();
        $this->patch(route('brands.activate', $brand))->assertNotFound();
        $this->put(route('brands.update', $brand), $this->data())->assertNotFound();
    }

    public function test_draft_restore_returns_to_draft(): void
    {
        $brand = $this->brand();
        $this->actingAs($this->user)->delete(route('brands.destroy', $brand))->assertRedirect();
        $this->patch(route('brands.restore', $brand))->assertRedirect();
        $this->assertSame('DRAFT', $brand->fresh()->status);
        $this->assertNull($brand->fresh()->activated_at);
    }

    public function test_pages_escape_content_and_show_operational_unit_count(): void
    {
        $brand = $this->brand(['name' => '<script>alert(1)</script>']);
        $brand->operationalUnits()->create(['organization_id' => $this->organization->id, 'code' => 'UNIT', 'name' => 'Unit', 'type' => 'BOOTH']);
        $this->actingAs($this->user);
        foreach (['index', 'create', 'show', 'edit'] as $page) {
            $response = $this->get(route('brands.'.$page, in_array($page, ['show', 'edit']) ? $brand : []));
            $response->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        }
        $this->get(route('brands.show', $brand))->assertViewHas('brand', fn ($data) => $data->operational_units_count === 1);
    }

    public function test_search_filters_counts_and_pagination_are_scoped(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->brand(['code' => 'B-'.$i, 'name' => 'Brand '.$i]);
        }
        $this->brand(['code' => 'SPECIAL', 'name' => 'Special Name', 'status' => 'ACTIVE', 'business_model' => 'RESTAURANT']);
        $this->actingAs($this->user)->get(route('brands.index'))->assertViewHas('brands', fn ($rows) => $rows->total() === 18 && $rows->count() === 15)
            ->assertViewHas('counts', ['all' => 18, 'active' => 1, 'draft' => 17]);
        foreach (['SPECIAL', 'Special Name'] as $search) {
            $this->get(route('brands.index', ['search' => $search, 'status' => 'ACTIVE', 'business_model' => 'RESTAURANT']))
                ->assertViewHas('brands', fn ($rows) => $rows->total() === 1);
        }
        $this->get(route('brands.index', ['search' => 'No such brand']))->assertSee('Tidak ada brand yang cocok');
    }

    public function test_settings_can_be_cleared_and_form_keeps_old_input(): void
    {
        $brand = $this->brand();
        $this->actingAs($this->user)->put(route('brands.update', $brand), $this->data(['settings' => null]))->assertRedirect();
        $this->assertNull($brand->fresh()->settings);
        $this->from(route('brands.edit', $brand))->put(route('brands.update', $brand), $this->data(['name' => 'Retained name', 'settings' => '{broken']))->assertRedirect(route('brands.edit', $brand));
        $this->get(route('brands.edit', $brand))->assertOk()->assertSee('Retained name')->assertSee('{broken');
    }

    public function test_audit_failure_rolls_back_status_change(): void
    {
        $brand = $this->brand();
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit failed');
        });
        try {
            $this->actingAs($this->user)->patch(route('brands.activate', $brand))->assertStatus(500);
            $this->assertSame('DRAFT', $brand->fresh()->status);
            $this->assertNull($brand->fresh()->activated_at);
        } finally {
            AuditLog::flushEventListeners();
        }
    }
}
