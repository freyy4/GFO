<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalUnitManagementTest extends TestCase
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
        $this->grant($this->user);
    }

    private function grant(User $user, ?OperationalUnit $unit = null, array $abilities = ['view', 'create', 'update', 'delete'], array $pivot = []): void
    {
        $role = Role::create(['organization_id' => $user->organization_id, 'code' => 'R-'.Role::count(), 'name' => 'Unit role', 'scope_type' => $unit ? 'OPERATIONAL_UNIT' : 'ORGANIZATION']);
        foreach ($abilities as $ability) {
            $permission = Permission::firstOrCreate(['code' => 'operational_units.'.$ability], ['name' => $ability, 'module' => 'operational_units']);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role, array_replace(['operational_unit_id' => $unit?->id], $pivot));
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['code' => 'UNIT-01', 'name' => 'Booth Garuta', 'type' => 'BOOTH', 'status' => 'ACTIVE', 'timezone' => 'Asia/Jakarta',
            'brand_id' => null, 'parent_id' => null, 'opens_at' => '22:00', 'closes_at' => '06:00', 'settings' => '{"note":"test"}'], $overrides);
    }

    private function unit(array $overrides = []): OperationalUnit
    {
        return OperationalUnit::create(array_replace(['organization_id' => $this->organization->id, 'code' => 'U-'.OperationalUnit::withTrashed()->count(),
            'name' => 'Unit', 'type' => 'BOOTH', 'status' => 'ACTIVE', 'timezone' => 'Asia/Jakarta'], $overrides));
    }

    public function test_crud_uses_trusted_organization_times_and_audit(): void
    {
        $this->actingAs($this->user)->post(route('operational-units.store'), $this->data(['organization_id' => 999, 'operational_unit_id' => 999]))->assertSessionHasNoErrors()->assertRedirect();
        $unit = OperationalUnit::firstOrFail();
        $this->assertSame($this->organization->id, $unit->organization_id);
        $this->assertSame('22:00', $unit->opens_at->format('H:i'));
        $this->assertStringStartsWith('22:00', $unit->getRawOriginal('opens_at'));
        $this->put(route('operational-units.update', $unit), $this->data(['name' => 'Updated', 'status' => 'INACTIVE']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('INACTIVE', $unit->fresh()->status);
        $this->delete(route('operational-units.destroy', $unit))->assertRedirect(route('operational-units.index'));
        $this->assertSoftDeleted($unit);
        $this->get(route('operational-units.show', $unit))->assertNotFound();
        $this->assertSame(['operational_units.create', 'operational_units.update', 'operational_units.archive'], AuditLog::orderBy('id')->pluck('event')->all());
        $log = AuditLog::where('event', 'operational_units.update')->firstOrFail();
        $this->assertSame('Booth Garuta', $log->old_values['name']);
        $this->assertSame('Updated', $log->new_values['name']);
    }

    public function test_cross_organization_records_and_relations_are_rejected(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $unit = $this->unit(['organization_id' => $other->id, 'name' => 'Secret Unit']);
        $brand = Brand::create(['organization_id' => $other->id, 'code' => 'B', 'name' => 'Secret Brand']);
        $this->actingAs($this->user);
        foreach (['show', 'edit'] as $action) {
            $this->get(route('operational-units.'.$action, $unit))->assertNotFound();
        }
        $this->put(route('operational-units.update', $unit), $this->data())->assertNotFound();
        $this->delete(route('operational-units.destroy', $unit))->assertNotFound();
        $this->get(route('operational-units.index'))->assertDontSee('Secret Unit');
        $this->get(route('operational-units.create'))->assertDontSee('Secret Brand')->assertDontSee('Secret Unit');
        $this->post(route('operational-units.store'), $this->data(['brand_id' => $brand->id]))->assertSessionHasErrors('brand_id');
        $this->post(route('operational-units.store'), $this->data(['parent_id' => $unit->id]))->assertSessionHasErrors('parent_id');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unit_scoped_roles_cannot_access_siblings_or_create(): void
    {
        $assigned = $this->unit(['name' => 'Assigned']);
        $sibling = $this->unit(['name' => 'Hidden Sibling']);
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $this->grant($user, $assigned);
        $this->actingAs($user)->get(route('operational-units.index'))->assertSee('Assigned')->assertDontSee('Hidden Sibling');
        $this->get(route('operational-units.show', $assigned))->assertOk();
        $this->get(route('operational-units.show', $sibling))->assertNotFound();
        $this->get(route('operational-units.create'))->assertForbidden();
        $this->post(route('operational-units.store'), $this->data())->assertForbidden();
        $this->put(route('operational-units.update', $sibling), $this->data())->assertNotFound();
        $this->delete(route('operational-units.destroy', $sibling))->assertNotFound();
        $this->put(route('operational-units.update', $assigned), $this->data(['parent_id' => $sibling->id]))->assertSessionHasErrors('parent_id');
        $this->put(route('operational-units.update', $assigned), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $assigned->update(['parent_id' => $sibling->id]);
        $this->get(route('operational-units.edit', $assigned))->assertOk()->assertSee('Induk saat ini (akses terbatas)')->assertDontSee('Hidden Sibling');
        $this->put(route('operational-units.update', $assigned), $this->data(['parent_id' => $sibling->id]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($sibling->id, $assigned->fresh()->parent_id);
    }

    public function test_guests_inactive_users_expired_and_foreign_roles_cannot_access(): void
    {
        $this->get(route('operational-units.index'))->assertRedirect(route('login'));
        $unit = $this->unit();
        $this->get(route('operational-units.show', $unit))->assertRedirect(route('login'));
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $this->actingAs($user)->get(route('operational-units.index'))->assertForbidden();
        $this->grant($user, null, ['view'], ['ends_at' => now()->subDay()]);
        $this->get(route('operational-units.index'))->assertForbidden();
        $this->grant($user, null, ['view'], ['starts_at' => now()->addDay()]);
        $this->get(route('operational-units.index'))->assertForbidden();
        $this->user->update(['status' => 'INACTIVE']);
        $this->actingAs($this->user)->get(route('operational-units.index'))->assertForbidden();
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $this->user->roles()->first()->update(['organization_id' => $other->id]);
        $this->user->update(['status' => 'ACTIVE']);
        $this->get(route('operational-units.index'))->assertForbidden();
    }

    public function test_read_only_role_cannot_mutate(): void
    {
        $unit = $this->unit();
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $this->grant($user, null, ['view']);
        $this->actingAs($user)->get(route('operational-units.show', $unit))->assertOk()->assertDontSee('Arsipkan');
        $this->get(route('operational-units.edit', $unit))->assertForbidden();
        $this->put(route('operational-units.update', $unit), $this->data())->assertForbidden();
        $this->delete(route('operational-units.destroy', $unit))->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_uniqueness_and_cycles(): void
    {
        $unit = $this->unit(['code' => 'UNIT-01']);
        $child = $this->unit(['parent_id' => $unit->id]);
        $this->actingAs($this->user)->post(route('operational-units.store'), $this->data())->assertSessionHasErrors('code');
        $this->put(route('operational-units.update', $unit), $this->data(['parent_id' => $unit->id]))->assertSessionHasErrors('parent_id');
        $this->put(route('operational-units.update', $unit), $this->data(['parent_id' => $child->id]))->assertSessionHasErrors('parent_id');
        $this->delete(route('operational-units.destroy', $unit))->assertSessionHasErrors('unit');
        $this->post(route('operational-units.store'), $this->data(['code' => 'bad code', 'type' => 'INVALID', 'status' => 'INVALID', 'timezone' => 'INVALID', 'latitude' => 91, 'longitude' => 181, 'geofence_radius_m' => -1, 'opens_at' => '25:00', 'settings' => '{bad']))
            ->assertSessionHasErrors(['code', 'type', 'status', 'timezone', 'latitude', 'longitude', 'geofence_radius_m', 'opens_at', 'settings']);
        $this->delete(route('operational-units.destroy', $child))->assertRedirect();
        $this->delete(route('operational-units.destroy', $unit))->assertRedirect();
        $this->post(route('operational-units.store'), $this->data())->assertSessionHasErrors('code');
    }

    public function test_pages_escape_values_and_keep_old_input(): void
    {
        $unit = $this->unit(['name' => '<script>alert(1)</script>']);
        $this->actingAs($this->user);
        foreach (['index', 'create', 'show', 'edit'] as $page) {
            $this->get(route('operational-units.'.$page, in_array($page, ['show', 'edit']) ? $unit : []))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        }
        $this->from(route('operational-units.edit', $unit))->put(route('operational-units.update', $unit), $this->data(['name' => 'Retained', 'settings' => '{bad']))->assertRedirect(route('operational-units.edit', $unit));
        $this->get(route('operational-units.edit', $unit))->assertSee('Retained')->assertSee('aria-invalid="true"', false);
    }

    public function test_search_filters_pagination_and_empty_state(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->unit();
        }
        $this->unit(['code' => 'SPECIAL', 'name' => 'Special Name', 'city' => 'Bandung', 'type' => 'WAREHOUSE', 'status' => 'INACTIVE']);
        $this->actingAs($this->user)->get(route('operational-units.index'))->assertViewHas('units', fn ($rows) => $rows->total() === 18 && $rows->count() === 15);
        foreach (['SPECIAL', 'Special Name', 'Bandung'] as $search) {
            $this->get(route('operational-units.index', ['search' => $search, 'type' => 'WAREHOUSE', 'status' => 'INACTIVE']))->assertViewHas('units', fn ($rows) => $rows->total() === 1);
        }
        $this->get(route('operational-units.index', ['search' => 'Missing']))->assertSee('Tidak ada unit yang cocok');
        $this->get(route('operational-units.index', ['status' => 'bad']))->assertSessionHasErrors('status');
    }

    public function test_valid_relations_and_optional_fields_can_be_saved_and_cleared(): void
    {
        $parent = $this->unit();
        $brand = Brand::create(['organization_id' => $this->organization->id, 'code' => 'B', 'name' => 'Brand']);
        $this->actingAs($this->user)->post(route('operational-units.store'), $this->data([
            'parent_id' => $parent->id, 'brand_id' => $brand->id, 'latitude' => -6.2, 'longitude' => 106.8,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $unit = OperationalUnit::where('code', 'UNIT-01')->firstOrFail();
        $this->assertSame($parent->id, $unit->parent_id);
        $this->assertSame($brand->id, $unit->brand_id);
        $this->put(route('operational-units.update', $unit), $this->data([
            'settings' => null, 'opens_at' => null, 'closes_at' => null, 'latitude' => null, 'longitude' => null,
            'organization_id' => 999, 'operational_unit_id' => $parent->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $unit->refresh();
        $this->assertSame($this->organization->id, $unit->organization_id);
        foreach (['settings', 'opens_at', 'closes_at', 'latitude', 'longitude', 'brand_id', 'parent_id'] as $field) {
            $this->assertNull($unit->$field);
        }
        $this->get(route('operational-units.index', ['search' => '0']))
            ->assertViewHas('units', fn ($rows) => $rows->total() === 2);
        $this->get(route('operational-units.edit', $unit))->assertOk();
    }

    public function test_audit_failure_rolls_back_mutations(): void
    {
        $unit = $this->unit();
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit failed');
        });
        try {
            $this->actingAs($this->user)->post(route('operational-units.store'), $this->data())->assertStatus(500);
            $this->assertDatabaseCount('operational_units', 1);
            $this->put(route('operational-units.update', $unit), $this->data(['name' => 'Changed']))->assertStatus(500);
            $this->assertSame('Unit', $unit->fresh()->name);
            $this->delete(route('operational-units.destroy', $unit))->assertStatus(500);
            $this->assertNotSoftDeleted($unit);
        } finally {
            AuditLog::flushEventListeners();
        }
    }
}
