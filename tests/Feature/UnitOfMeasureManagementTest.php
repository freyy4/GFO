<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\UomConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitOfMeasureManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->organization = Organization::create(['code' => 'GFO', 'name' => 'Garuta']);
        $this->admin = $this->user();
        $this->assign($this->admin, 'SUPER_ADMIN', ['is_system' => true]);
    }

    private function user(): User
    {
        return User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
    }

    private function assign(User $user, string $code, array $attributes = [], array $pivot = []): Role
    {
        $role = Role::create(array_replace(['organization_id' => $user->organization_id, 'code' => $code,
            'name' => $code, 'scope_type' => 'ORGANIZATION', 'is_system' => false], $attributes));
        $user->roles()->attach($role, $pivot);

        return $role;
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['code' => 'KG', 'name' => 'Kilogram', 'dimension' => 'MASS', 'decimal_places' => 3], $overrides);
    }

    private function uom(array $overrides = []): UnitOfMeasure
    {
        return UnitOfMeasure::create($this->data($overrides));
    }

    public function test_super_admin_crud_and_audit_with_global_code_normalization(): void
    {
        $this->actingAs($this->admin)->post(route('uoms.store'), $this->data(['code' => ' kg ']))->assertSessionHasNoErrors()->assertRedirect();
        $uom = UnitOfMeasure::firstOrFail();
        $this->assertSame('KG', $uom->code);
        $this->put(route('uoms.update', $uom), $this->data(['name' => 'Kilogram diperbarui', 'dimension' => null]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($uom->fresh()->dimension);
        $this->delete(route('uoms.destroy', $uom))->assertRedirect(route('uoms.index'));
        $this->assertDatabaseMissing('units_of_measure', ['id' => $uom->id]);
        $this->assertSame(['uoms.create', 'uoms.update', 'uoms.delete'], AuditLog::orderBy('id')->pluck('event')->all());
        $log = AuditLog::where('event', 'uoms.update')->firstOrFail();
        $this->assertSame('Kilogram', $log->old_values['name']);
        $this->assertSame('Kilogram diperbarui', $log->new_values['name']);
        $this->assertSame($this->organization->id, $log->organization_id);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->get(route('uoms.show', $uom))->assertNotFound();
    }

    public function test_guest_unassigned_inactive_and_missing_organization_are_denied(): void
    {
        $uom = $this->uom();
        $this->get(route('uoms.index'))->assertRedirect(route('login'));
        $this->post(route('uoms.store'), $this->data())->assertRedirect(route('login'));
        $this->get(route('uoms.show', $uom))->assertRedirect(route('login'));
        $this->actingAs($this->user())->get(route('uoms.index'))->assertForbidden();
        $this->get(route('uoms.show', $uom))->assertForbidden();
        $this->admin->update(['status' => 'INACTIVE']);
        $this->actingAs($this->admin)->get(route('uoms.index'))->assertForbidden();
        $this->admin->update(['status' => 'ACTIVE']);
        $this->organization->update(['status' => 'INACTIVE']);
        $this->get(route('uoms.index'))->assertForbidden();
        $this->organization->delete();
        $this->get(route('uoms.index'))->assertForbidden();
    }

    public function test_valid_read_role_cannot_mutate_global_catalog(): void
    {
        $uom = $this->uom();
        $viewer = $this->user();
        $role = $this->assign($viewer, 'PROCUREMENT');
        foreach (['view', 'create', 'update', 'delete'] as $ability) {
            $permission = Permission::create(['code' => 'uoms.'.$ability, 'name' => $ability, 'module' => 'uoms']);
            $role->permissions()->attach($permission);
        }
        $this->actingAs($viewer)->get(route('uoms.index'))->assertOk()->assertDontSee('Tambah satuan');
        $this->get(route('uoms.show', $uom))->assertOk()->assertDontSee('Hapus satuan');
        $this->get(route('uoms.create'))->assertForbidden();
        $this->get(route('uoms.edit', $uom))->assertForbidden();
        $this->post(route('uoms.store'), $this->data(['code' => 'G']))->assertForbidden();
        $this->put(route('uoms.update', $uom), $this->data())->assertForbidden();
        $this->delete(route('uoms.destroy', $uom))->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_role_and_operational_unit_scope_cannot_be_forged(): void
    {
        $viewer = $this->user();
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $unit = OperationalUnit::create(['organization_id' => $other->id, 'code' => 'FOREIGN', 'name' => 'Secret Booth', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $this->assign($viewer, 'SUPER_ADMIN', ['organization_id' => $other->id, 'is_system' => true]);
        $this->assign($viewer, 'PROCUREMENT', [], ['operational_unit_id' => $unit->id]);
        $this->assign($viewer, 'KITCHEN_MANAGER', [], ['ends_at' => now()->subMinute()]);
        $this->assign($viewer, 'WAREHOUSE_MANAGER', [], ['starts_at' => now()->addDay()]);
        $this->actingAs($viewer)->get(route('uoms.index', ['organization_id' => $other->id, 'operational_unit_id' => $unit->id]))->assertForbidden();
        $this->post(route('uoms.store'), $this->data())->assertForbidden();
        $unit->update(['organization_id' => $this->organization->id]);
        $this->get(route('uoms.index'))->assertOk()->assertDontSee('Secret Booth');
        $unit->delete();
        $this->get(route('uoms.index'))->assertForbidden();
    }

    public function test_unit_scoped_super_admin_cannot_change_global_definitions(): void
    {
        $user = $this->user();
        $unit = OperationalUnit::create(['organization_id' => $this->organization->id, 'code' => 'U', 'name' => 'Unit', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $role = $this->admin->roles()->firstOrFail();
        $user->roles()->attach($role, ['operational_unit_id' => $unit->id]);
        $this->actingAs($user)->get(route('uoms.index'))->assertOk();
        $this->post(route('uoms.store'), $this->data())->assertForbidden();
    }

    public function test_permission_only_reader_and_global_system_role_are_supported(): void
    {
        $viewer = $this->user();
        $role = $this->assign($viewer, 'CUSTOM');
        $permission = Permission::create(['code' => 'uoms.view', 'name' => 'Read UOM', 'module' => 'uoms']);
        $role->permissions()->attach($permission);
        $this->actingAs($viewer)->get(route('uoms.index'))->assertOk();
        $this->assign($viewer, 'SUPER_ADMIN', ['organization_id' => null, 'is_system' => true]);
        $this->post(route('uoms.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_validation_prevents_spoofed_fields_invalid_precision_and_global_duplicates(): void
    {
        $uom = $this->uom(['code' => 'kg']);
        $this->actingAs($this->admin)->post(route('uoms.store'), $this->data())->assertSessionHasErrors('code');
        $this->post(route('uoms.store'), $this->data(['code' => 'bad code', 'name' => '', 'dimension' => str_repeat('a', 31), 'decimal_places' => 5,
            'organization_id' => 123, 'operational_unit_id' => 123, 'status' => 'ACTIVE']))
            ->assertSessionHasErrors(['code', 'name', 'dimension', 'decimal_places', 'organization_id', 'operational_unit_id', 'status']);
        $this->post(route('uoms.store'), $this->data(['code' => [], 'decimal_places' => 1.5]))->assertSessionHasErrors(['code', 'decimal_places']);
        $this->put(route('uoms.update', $uom), $this->data(['organization_id' => null, 'is_active' => true, 'deleted_at' => now()]))
            ->assertSessionHasErrors(['organization_id', 'is_active', 'deleted_at']);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->put(route('uoms.update', $uom), $this->data())->assertSessionHasNoErrors();
    }

    public function test_used_uom_cannot_be_changed_or_deleted_even_for_soft_deleted_foreign_inventory(): void
    {
        $uom = $this->uom();
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Secret Organization']);
        $inventory = InventoryItem::create(['organization_id' => $other->id, 'base_uom_id' => $uom->id, 'sku' => 'SECRET', 'name' => 'Secret Ingredient', 'item_type' => 'RAW_MATERIAL']);
        $inventory->delete();
        $this->actingAs($this->admin)->get(route('uoms.show', $uom))->assertOk()->assertSee('Terpakai')->assertDontSee('Secret Ingredient')->assertDontSee('Secret Organization');
        $this->get(route('uoms.edit', $uom))->assertOk()->assertSee('disabled', false)->assertDontSee('Simpan satuan');
        $this->put(route('uoms.update', $uom), $this->data(['name' => 'Changed']))->assertSessionHasErrors('uom');
        $this->delete(route('uoms.destroy', $uom))->assertSessionHasErrors('uom');
        $this->assertSame('Kilogram', $uom->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_conversions_protect_both_sides_from_cascade_deletion(): void
    {
        $kg = $this->uom();
        $g = $this->uom(['code' => 'G', 'name' => 'Gram']);
        UomConversion::create(['from_uom_id' => $kg->id, 'to_uom_id' => $g->id, 'factor' => 1000]);
        $this->actingAs($this->admin);
        foreach ([$kg, $g] as $uom) {
            $this->delete(route('uoms.destroy', $uom))->assertSessionHasErrors('uom');
            $this->put(route('uoms.update', $uom), $this->data(['code' => $uom->code]))->assertSessionHasErrors('uom');
        }
        $this->assertDatabaseCount('units_of_measure', 2);
        $this->assertDatabaseCount('uom_conversions', 1);
    }

    public function test_search_filter_pagination_and_empty_state(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->uom(['code' => 'U-'.$i, 'name' => 'Satuan '.$i, 'dimension' => 'COUNT']);
        }
        $kg = $this->uom();
        InventoryItem::create(['organization_id' => $this->organization->id, 'base_uom_id' => $kg->id, 'sku' => 'ITEM', 'name' => 'Item', 'item_type' => 'RAW_MATERIAL']);
        $this->actingAs($this->admin)->get(route('uoms.index'))->assertViewHas('uoms', fn ($rows) => $rows->total() === 18 && $rows->count() === 15);
        foreach (['KG', 'Kilogram'] as $search) {
            $this->get(route('uoms.index', ['search' => $search, 'dimension' => 'MASS', 'usage' => 'used']))
                ->assertViewHas('uoms', fn ($rows) => $rows->total() === 1)->assertSee('Terpakai');
        }
        $this->get(route('uoms.index', ['usage' => 'unused']))->assertViewHas('uoms', fn ($rows) => $rows->total() === 17);
        $this->get(route('uoms.index', ['search' => '0']))->assertViewHas('uoms', fn ($rows) => $rows->total() === 2);
        $this->get(route('uoms.index', ['search' => 'No match']))->assertSee('Belum ada satuan yang cocok');
        $this->get(route('uoms.index', ['usage' => 'bad', 'page' => 0]))->assertSessionHasErrors(['usage', 'page']);
    }

    public function test_all_pages_escape_content_and_retain_validation_input(): void
    {
        $uom = $this->uom(['name' => '<script>alert(1)</script>']);
        $this->actingAs($this->admin);
        foreach (['index', 'create', 'show', 'edit'] as $action) {
            $this->get(route('uoms.'.$action, in_array($action, ['show', 'edit']) ? $uom : []))
                ->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('Sidebar admin');
        }
        $this->from(route('uoms.edit', $uom))->put(route('uoms.update', $uom), $this->data(['name' => 'Retained', 'decimal_places' => -1]))->assertRedirect(route('uoms.edit', $uom));
        $this->get(route('uoms.edit', $uom))->assertSee('Retained')->assertSee('aria-invalid="true"', false);
    }

    public function test_audit_failure_rolls_back_every_mutation(): void
    {
        $uom = $this->uom();
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->actingAs($this->admin)->post(route('uoms.store'), $this->data(['code' => 'G']))->assertStatus(500);
            $this->assertDatabaseCount('units_of_measure', 1);
            $this->put(route('uoms.update', $uom), $this->data(['name' => 'Changed']))->assertStatus(500);
            $this->assertSame('Kilogram', $uom->fresh()->name);
            $this->delete(route('uoms.destroy', $uom))->assertStatus(500);
            $this->assertDatabaseHas('units_of_measure', ['id' => $uom->id]);
        } finally {
            AuditLog::flushEventListeners();
        }
    }
}
