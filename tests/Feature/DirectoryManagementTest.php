<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Support\DirectoryDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private OperationalUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->organization = Organization::create(['code' => 'GFO', 'name' => 'Garuta']);
        $this->admin = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->organization->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'scope_type' => 'ORGANIZATION', 'is_system' => true]);
        $this->admin->roles()->attach($role);
        $this->unit = OperationalUnit::create(['organization_id' => $this->organization->id, 'code' => 'U1', 'name' => 'Unit One', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
    }

    private function data(string $module, array $overrides = []): array
    {
        return array_replace(match ($module) {
            'suppliers' => ['code' => 'SUP-01', 'name' => 'Supplier One', 'default_lead_time_hours' => 12, 'approval_status' => 'APPROVED', 'risk_level' => 'LOW'],
            'positions' => ['code' => 'POS-01', 'name' => 'Cook', 'work_area' => 'KITCHEN', 'required_skills' => '["Cooking"]'],
            'employees' => ['employee_no' => 'EMP-01', 'full_name' => 'Employee One', 'home_unit_id' => $this->unit->id,
                'employment_type' => 'PERMANENT', 'status' => 'ACTIVE', 'base_salary' => '4000000.00', 'joined_on' => '2026-01-01', 'emergency_contact' => '{"name":"Family"}'],
        }, $overrides);
    }

    private function record(string $module, array $overrides = []): mixed
    {
        $class = DirectoryDefinition::model($module);
        $data = $this->data($module, $overrides);
        foreach (['required_skills', 'emergency_contact'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = json_decode($data[$key], true);
            }
        }

        return $class::create(['organization_id' => $this->organization->id, ...$data]);
    }

    private function reader(string $module, ?int $unit = null, array $abilities = ['view']): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->organization->id, 'code' => 'ROLE-'.$user->id,
            'name' => 'Reader', 'scope_type' => $unit ? 'OPERATIONAL_UNIT' : 'ORGANIZATION']);
        foreach ($abilities as $ability) {
            $permission = Permission::firstOrCreate(['code' => $module.'.'.$ability], ['name' => $ability, 'module' => $module]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role, ['operational_unit_id' => $unit]);

        return $user;
    }

    public function test_crud_pages_and_audits_for_all_three_directories(): void
    {
        $this->actingAs($this->admin);
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $this->get(route($module.'.index'))->assertOk()->assertSee('Belum ada data');
            $this->get(route($module.'.create'))->assertOk();
            $this->post(route($module.'.store'), $this->data($module))->assertRedirect()->assertSessionHasNoErrors();
            $class = DirectoryDefinition::model($module);
            $record = $class::firstOrFail();
            $this->get(route($module.'.show', $record))->assertOk();
            $this->get(route($module.'.edit', $record))->assertOk();
            $name = $module === 'employees' ? 'full_name' : 'name';
            $this->put(route($module.'.update', $record), $this->data($module, [$name => 'Updated']))->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('Updated', $record->fresh()->$name);
            $this->delete(route($module.'.destroy', $record))->assertRedirect();
            if ($module !== 'positions') {
                $this->assertSoftDeleted($record);
                $this->get(route($module.'.show', $record))->assertOk()->assertSee('Pulihkan');
                $this->patch(route($module.'.restore', $record))->assertRedirect();
                $this->assertNull($record->fresh()->deleted_at);
                $this->assertSame($module === 'employees' ? 'INACTIVE' : 'PENDING', $record->fresh()->getAttribute($module === 'employees' ? 'status' : 'approval_status'));
            } else {
                $this->assertModelMissing($record);
            }
            $this->assertDatabaseHas('audit_logs', ['event' => $module.'.update', 'organization_id' => $this->organization->id, 'user_id' => $this->admin->id]);
        }
        $this->assertDatabaseCount('audit_logs', 11);
    }

    public function test_guests_and_unprivileged_users_are_denied(): void
    {
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $this->get(route($module.'.index'))->assertRedirect(route('login'));
        }
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'ACTIVE']);
        $this->actingAs($user);
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $this->get(route($module.'.index'))->assertForbidden();
            $this->get(route($module.'.create'))->assertForbidden();
            $this->post(route($module.'.store'), $this->data($module))->assertForbidden();
        }
    }

    public function test_read_permission_does_not_allow_mutations(): void
    {
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $record = $this->record($module);
            $this->actingAs($this->reader($module));
            $this->get(route($module.'.index'))->assertOk();
            $this->get(route($module.'.show', $record))->assertOk();
            $this->get(route($module.'.create'))->assertForbidden();
            $this->put(route($module.'.update', $record), $this->data($module))->assertForbidden();
            $this->delete(route($module.'.destroy', $record))->assertForbidden();
        }
    }

    public function test_explicit_write_permissions_authorize_organization_managers(): void
    {
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $this->actingAs($this->reader($module, null, ['view', 'create', 'update', 'delete']));
            $this->post(route($module.'.store'), $this->data($module))->assertRedirect()->assertSessionHasNoErrors();
        }
    }

    public function test_cross_organization_binding_blocks_every_record_action(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $this->actingAs($this->admin);
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $record = $this->record($module, ['organization_id' => $other->id]);
            foreach (['show', 'edit'] as $action) {
                $this->get(route($module.'.'.$action, $record))->assertNotFound();
            }
            $this->put(route($module.'.update', $record), $this->data($module))->assertNotFound();
            $this->delete(route($module.'.destroy', $record))->assertNotFound();
            if ($module !== 'positions') {
                $record->delete();
                $this->patch(route($module.'.restore', $record))->assertNotFound();
            }
            $this->get(route($module.'.index', ['archived' => 'all']))->assertViewHas('records', fn ($records) => $records->total() === 0);
        }
    }

    public function test_employee_unit_scope_and_filters_cannot_be_forged(): void
    {
        $employee = $this->record('employees');
        $unit = OperationalUnit::create(['organization_id' => $this->organization->id, 'code' => 'U2', 'name' => 'Unit Two', 'type' => 'BOOTH']);
        $hidden = $this->record('employees', ['employee_no' => 'HIDDEN', 'full_name' => 'Hidden Person', 'home_unit_id' => $unit->id]);
        $this->actingAs($this->reader('employees', $this->unit->id, ['view', 'create', 'update', 'delete']));
        $this->get(route('employees.index'))->assertOk()->assertSee('Employee One')->assertDontSee('Hidden Person');
        $this->get(route('employees.index', ['home_unit_id' => $unit->id]))->assertViewHas('records', fn ($rows) => $rows->total() === 0);
        $this->get(route('employees.show', $hidden))->assertNotFound();
        $this->get(route('employees.show', $employee))->assertOk();
        $this->put(route('employees.update', $employee), $this->data('employees'))->assertForbidden();
        $this->post(route('employees.store'), $this->data('employees'))->assertForbidden();
    }

    public function test_forged_scope_and_employee_references_are_rejected(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $position = Position::create(['organization_id' => $other->id, 'code' => 'OTHER', 'name' => 'Other']);
        $user = User::factory()->create(['organization_id' => $other->id]);
        $unit = OperationalUnit::create(['organization_id' => $other->id, 'code' => 'OTHER', 'name' => 'Other', 'type' => 'BOOTH']);
        $this->actingAs($this->admin);
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $this->post(route($module.'.store'), $this->data($module, ['organization_id' => null, 'operational_unit_id' => $unit->id, 'deleted_at' => null]))
                ->assertSessionHasErrors(['organization_id', 'operational_unit_id', 'deleted_at']);
        }
        $this->post(route('employees.store'), $this->data('employees', ['position_id' => $position->id, 'user_id' => $user->id, 'home_unit_id' => $unit->id]))
            ->assertSessionHasErrors(['position_id', 'user_id', 'home_unit_id']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_handles_dates_numbers_json_and_enums(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('suppliers.store'), $this->data('suppliers', ['default_lead_time_hours' => -1, 'approval_status' => 'INVALID',
            'risk_level' => 'INVALID', 'performance_score' => '10000', 'email' => 'invalid']))
            ->assertSessionHasErrors(['default_lead_time_hours', 'approval_status', 'risk_level', 'performance_score', 'email']);
        $this->post(route('positions.store'), $this->data('positions', ['required_skills' => '"scalar"']))->assertSessionHasErrors('required_skills');
        $this->post(route('employees.store'), $this->data('employees', ['ended_on' => '2025-01-01', 'base_salary' => '10.001', 'emergency_contact' => '{broken', 'employment_type' => 'INVALID']))
            ->assertSessionHasErrors(['ended_on', 'base_salary', 'emergency_contact', 'employment_type']);
    }

    public function test_unique_codes_include_archives_and_ignore_current_record(): void
    {
        $this->actingAs($this->admin);
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $record = $this->record($module);
            $code = $module === 'employees' ? 'employee_no' : 'code';
            $this->post(route($module.'.store'), $this->data($module))->assertSessionHasErrors($code);
            $this->post(route($module.'.store'), $this->data($module, [$code => strtolower($record->$code)]))->assertSessionHasErrors($code);
            $this->put(route($module.'.update', $record), $this->data($module))->assertSessionHasNoErrors();
            if ($module !== 'positions') {
                $record->delete();
                $this->post(route($module.'.store'), $this->data($module))->assertSessionHasErrors($code);
            }
        }
    }

    public function test_employee_user_link_is_unique_including_archives(): void
    {
        $employee = $this->record('employees', ['user_id' => $this->admin->id]);
        $employee->delete();
        $this->actingAs($this->admin)->post(route('employees.store'), $this->data('employees', ['employee_no' => 'EMP-02', 'user_id' => $this->admin->id]))->assertSessionHasErrors('user_id');
    }

    public function test_position_in_use_cannot_be_deleted_even_for_archived_employee(): void
    {
        $position = $this->record('positions');
        $employee = $this->record('employees', ['position_id' => $position->id]);
        $employee->delete();
        $this->actingAs($this->admin)->delete(route('positions.destroy', $position))->assertSessionHasErrors('record');
        $this->assertModelExists($position);
        $this->assertSame($position->id, $employee->fresh()->position_id);
    }

    public function test_expired_foreign_and_inactive_assignments_do_not_grant_access(): void
    {
        $reader = $this->reader('employees', $this->unit->id);
        $reader->roles()->updateExistingPivot($reader->roles->first()->id, ['ends_at' => now()->subDay()]);
        $this->actingAs($reader)->get(route('employees.index'))->assertForbidden();
        $reader->roles()->updateExistingPivot($reader->roles->first()->id, ['ends_at' => null]);
        $this->unit->update(['status' => 'INACTIVE']);
        $this->get(route('employees.index'))->assertForbidden();
        $this->organization->update(['status' => 'INACTIVE']);
        $this->actingAs($this->admin)->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_search_filters_pagination_and_escaped_output(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->record('suppliers', ['code' => 'S-'.$i, 'name' => 'Supplier '.$i]);
        }
        $target = $this->record('suppliers', ['code' => 'TARGET', 'name' => '<script>secret()</script>', 'risk_level' => 'HIGH']);
        $this->actingAs($this->admin)->get(route('suppliers.index'))->assertViewHas('records', fn ($rows) => $rows->total() === 17 && $rows->count() === 15);
        $this->get(route('suppliers.index', ['search' => 'TARGET', 'risk_level' => 'HIGH', 'approval_status' => 'APPROVED']))
            ->assertViewHas('records', fn ($rows) => $rows->total() === 1)->assertDontSee('<script>secret()</script>', false);
        $target->delete();
        $this->get(route('suppliers.index', ['archived' => 'yes']))->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->get(route('suppliers.show', $target))->assertDontSee('<script>secret()</script>', false);
    }

    public function test_audit_failure_rolls_back_create_and_delete(): void
    {
        $supplier = $this->record('suppliers');
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->actingAs($this->admin)->post(route('employees.store'), $this->data('employees'))->assertStatus(500);
            $this->assertDatabaseCount('employees', 0);
            $this->delete(route('suppliers.destroy', $supplier))->assertStatus(500);
            $this->assertNull($supplier->fresh()->deleted_at);
            $this->assertSame('APPROVED', $supplier->fresh()->approval_status);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_foreign_roles_and_forged_super_admin_do_not_grant_access(): void
    {
        $reader = $this->reader('employees');
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $reader->roles->first()->update(['organization_id' => $other->id]);
        $this->actingAs($reader)->get(route('employees.index'))->assertForbidden();
        $this->admin->roles()->first()->update(['code' => 'ORIGINAL_ADMIN']);
        $reader->roles->first()->update(['organization_id' => $this->organization->id, 'code' => 'SUPER_ADMIN', 'is_system' => false]);
        $this->get(route('employees.index'))->assertOk();
        $this->post(route('employees.store'), $this->data('employees'))->assertForbidden();
    }

    public function test_employee_and_position_filters_and_old_input(): void
    {
        $position = $this->record('positions');
        $employee = $this->record('employees', ['position_id' => $position->id]);
        $this->actingAs($this->admin);
        $this->get(route('positions.index', ['search' => 'Cook', 'work_area' => 'KITCHEN']))
            ->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->get(route('employees.index', ['search' => 'Employee', 'status' => 'ACTIVE', 'employment_type' => 'PERMANENT', 'position_id' => $position->id, 'home_unit_id' => $this->unit->id]))
            ->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->from(route('employees.edit', $employee))->put(route('employees.update', $employee), $this->data('employees', ['full_name' => 'Retained name', 'emergency_contact' => '{invalid']))
            ->assertRedirect(route('employees.edit', $employee))->assertSessionHasErrors('emergency_contact');
        $this->get(route('employees.edit', $employee))->assertOk()->assertSee('Retained name')->assertSee('{invalid');
        $employee->delete();
        $this->get(route('employees.edit', $employee))->assertNotFound();
        $this->put(route('employees.update', $employee), $this->data('employees'))->assertNotFound();
    }

    public function test_mutations_require_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->actingAs($this->admin);
        foreach (['suppliers', 'positions', 'employees'] as $module) {
            $record = $this->record($module);
            $this->post(route($module.'.store'), $this->data($module))->assertStatus(419);
            $this->put(route($module.'.update', $record), $this->data($module))->assertStatus(419);
            $this->delete(route($module.'.destroy', $record))->assertStatus(419);
        }
    }
}
