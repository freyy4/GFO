<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CampaignMetric;
use App\Models\Employee;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProductionPlan;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Brand $brand;

    private OperationalUnit $unit;

    private OperationalUnit $sibling;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->org = Organization::create(['code' => 'GFO', 'name' => 'Garuta', 'timezone' => 'Asia/Jakarta']);
        $this->brand = Brand::create(['organization_id' => $this->org->id, 'code' => 'B', 'name' => 'Brand']);
        $this->unit = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $this->brand->id, 'code' => 'U1', 'name' => 'Allowed Unit', 'type' => 'BOOTH']);
        $this->sibling = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $this->brand->id, 'code' => 'U2', 'name' => 'Sibling Unit', 'type' => 'BOOTH']);
        $this->admin = $this->user('SUPER_ADMIN');
    }

    private function user(string $role, ?int $unitId = null): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $this->assign($user, $role, $unitId);

        return $user;
    }

    private function assign(User $user, string $code, ?int $unitId = null): Role
    {
        $role = Role::firstOrCreate(['organization_id' => $this->org->id, 'code' => $code],
            ['name' => $code, 'scope_type' => $unitId ? 'OPERATIONAL_UNIT' : 'ORGANIZATION', 'is_system' => $code === 'SUPER_ADMIN']);
        $user->roles()->attach($role, ['operational_unit_id' => $unitId]);

        return $role;
    }

    private function filters(array $overrides = []): array
    {
        return array_replace(['from' => '2026-09-01', 'to' => '2026-09-30'], $overrides);
    }

    private function sale(array $overrides = []): SalesOrder
    {
        return SalesOrder::create(array_replace(['organization_id' => $this->org->id, 'brand_id' => $this->brand->id,
            'operational_unit_id' => $this->unit->id, 'number' => 'SALE-'.SalesOrder::withTrashed()->count(),
            'ordered_at' => '2026-09-10 05:00:00', 'status' => 'COMPLETED', 'total_amount' => '100.00'], $overrides));
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::create(array_replace(['organization_id' => $this->org->id, 'home_unit_id' => $this->unit->id,
            'employee_no' => 'EMP-'.Employee::withTrashed()->count(), 'full_name' => 'Employee Name', 'status' => 'ACTIVE'], $overrides));
    }

    private function campaign(array $targets = [], array $overrides = []): Campaign
    {
        $campaign = Campaign::create(array_replace(['brand_id' => $this->brand->id, 'code' => 'C-'.Campaign::count(),
            'name' => 'Campaign '.Campaign::count(), 'campaign_type' => 'AWARENESS', 'channel' => 'SOCIAL', 'objective' => 'SALES',
            'starts_at' => '2026-09-01 00:00:00', 'ends_at' => '2026-09-30 23:00:00', 'status' => 'ACTIVE'], $overrides));
        foreach ($targets as $unitId) {
            $campaign->campaignTargets()->create(['operational_unit_id' => $unitId]);
        }

        return $campaign;
    }

    public function test_all_dashboards_require_authentication_and_render_empty_states(): void
    {
        foreach (['dashboard', 'dashboards.operations', 'dashboards.hr', 'dashboards.growth'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
        $this->actingAs($this->admin);
        $this->get(route('dashboard'))->assertOk()->assertSee('Allowed Unit')->assertDontSee('PREVIEW LAYOUT');
        foreach (['operations', 'hr', 'growth'] as $domain) {
            $this->get(route('dashboards.'.$domain, $this->filters()))->assertOk()->assertSee('Belum ada data sesuai filter');
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_roles_and_permissions_authorize_only_their_dashboard(): void
    {
        foreach (['OPERATIONS_MANAGER' => 'operations', 'HR' => 'hr', 'MARKETING' => 'growth', 'DIRECTOR' => 'growth'] as $role => $allowed) {
            $this->actingAs($this->user($role));
            foreach (['operations', 'hr', 'growth'] as $domain) {
                $response = $this->get(route('dashboards.'.$domain, $this->filters()));
                $domain === $allowed ? $response->assertOk() : $response->assertForbidden();
            }
        }
        $user = $this->user('CUSTOM');
        $permission = Permission::create(['code' => 'dashboards.hr.view', 'name' => 'HR dashboard', 'module' => 'dashboards']);
        $user->roles->first()->permissions()->attach($permission);
        $this->actingAs($user)->get(route('dashboards.hr'))->assertOk();
    }

    public function test_operations_totals_search_status_and_timezone_boundaries(): void
    {
        $this->sale(['number' => 'IN', 'ordered_at' => '2026-08-31 17:00:00', 'total_amount' => 150]);
        $this->sale(['ordered_at' => '2026-08-31 16:59:59', 'total_amount' => 1000]);
        $this->sale(['ordered_at' => '2026-09-30 16:59:59', 'total_amount' => 50]);
        $this->sale(['ordered_at' => '2026-09-30 17:00:00', 'total_amount' => 2000]);
        $this->sale(['status' => 'CANCELLED', 'total_amount' => 999]);
        $this->sale(['total_amount' => 9999])->delete();
        ProductionPlan::create(['brand_id' => $this->brand->id, 'production_unit_id' => $this->unit->id, 'number' => 'PLAN', 'production_date' => '2026-09-15']);
        $this->actingAs($this->admin)->get(route('dashboards.operations', $this->filters()))->assertOk()
            ->assertViewHas('cards', fn ($cards) => $cards[0][1] === 3 && (float) $cards[1][1] === 200.0 && (float) $cards[2][1] === 100.0 && $cards[3][1] === 1);
        $this->get(route('dashboards.operations', $this->filters(['search' => 'IN', 'status' => 'COMPLETED'])))
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1)->assertViewHas('cards', fn ($cards) => (float) $cards[1][1] === 150.0);
    }

    public function test_scopes_do_not_combine_an_hr_organization_role_with_operations_unit_role(): void
    {
        $local = $this->sale(['number' => 'LOCAL']);
        $this->sale(['number' => 'HIDDEN', 'operational_unit_id' => $this->sibling->id, 'total_amount' => 9999]);
        $user = $this->user('BOOTH_SUPERVISOR', $this->unit->id);
        $this->assign($user, 'HR');
        $this->actingAs($user)->get(route('dashboards.operations', $this->filters()))->assertOk()->assertDontSee('HIDDEN')->assertDontSee('Sibling Unit')
            ->assertViewHas('cards', fn ($cards) => (float) $cards[1][1] === 100.0);
        $this->getJson(route('dashboards.operations', $this->filters(['unit' => $this->sibling->id])))->assertUnprocessable()->assertJsonValidationErrors('unit');
    }

    public function test_cross_organization_and_corrupt_links_do_not_leak(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $brand = Brand::create(['organization_id' => $other->id, 'code' => 'OTHER', 'name' => 'Other']);
        $unit = OperationalUnit::create(['organization_id' => $other->id, 'code' => 'OTHER', 'name' => 'Secret Unit', 'type' => 'BOOTH']);
        $this->sale(['number' => 'SECRET1', 'organization_id' => $other->id]);
        $this->sale(['number' => 'SECRET2', 'brand_id' => $brand->id]);
        $this->sale(['number' => 'SECRET3', 'operational_unit_id' => $unit->id]);
        $employee = $this->employee(['organization_id' => $other->id, 'full_name' => 'Secret Employee']);
        AttendanceRecord::create(['employee_id' => $employee->id, 'operational_unit_id' => $this->unit->id, 'business_date' => '2026-09-10']);
        $this->campaign([], ['brand_id' => $brand->id, 'name' => 'Secret Campaign']);
        $this->actingAs($this->admin);
        foreach (['operations', 'hr', 'growth'] as $domain) {
            $this->get(route('dashboards.'.$domain, $this->filters()))->assertOk()->assertDontSee('Secret')->assertDontSee('SECRET')
                ->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
            $this->getJson(route('dashboards.'.$domain, $this->filters(['unit' => $unit->id])))->assertUnprocessable();
        }
    }

    public function test_hr_uses_work_unit_and_business_date_without_salary_or_private_fields(): void
    {
        $employee = $this->employee(['home_unit_id' => $this->sibling->id, 'base_salary' => '9876543', 'email' => 'private@example.com']);
        AttendanceRecord::create(['employee_id' => $employee->id, 'operational_unit_id' => $this->unit->id, 'business_date' => '2026-09-10', 'status' => 'APPROVED', 'late_minutes' => 7]);
        AttendanceRecord::create(['employee_id' => $employee->id, 'operational_unit_id' => $this->sibling->id, 'business_date' => '2026-09-10', 'status' => 'PENDING']);
        ShiftAssignment::create(['operational_unit_id' => $this->unit->id, 'employee_id' => $employee->id, 'starts_at' => '2026-09-10 02:00:00', 'ends_at' => '2026-09-10 10:00:00']);
        $this->actingAs($this->user('HR', $this->unit->id))->get(route('dashboards.hr', $this->filters(['search' => 'Employee', 'status' => 'APPROVED'])))
            ->assertOk()->assertSee('Employee Name')->assertDontSee('9876543')->assertDontSee('private@example.com')
            ->assertViewHas('cards', fn ($cards) => $cards[0][1] === 0 && $cards[1][1] === 1 && $cards[2][1] === 1 && $cards[3][1] === 1);
    }

    public function test_growth_scoped_campaigns_require_all_targets_in_scope(): void
    {
        $allowed = $this->campaign([$this->unit->id], ['name' => 'Allowed Campaign']);
        $mixed = $this->campaign([$this->unit->id, $this->sibling->id], ['name' => 'Mixed Secret']);
        $this->campaign([], ['name' => 'Global Secret']);
        $this->campaign([$this->unit->id, null], ['name' => 'Null Target Secret']);
        foreach ([$allowed, $mixed] as $campaign) {
            CampaignMetric::create(['campaign_id' => $campaign->id, 'metric_date' => '2026-09-10', 'spend' => 100, 'attributed_revenue' => 300]);
        }
        $this->actingAs($this->user('MARKETING', $this->unit->id))->get(route('dashboards.growth', $this->filters()))->assertOk()
            ->assertSee('Allowed Campaign')->assertDontSee('Secret')->assertViewHas('rows', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('cards', fn ($cards) => (float) $cards[1][1] === 100.0 && (float) $cards[2][1] === 300.0 && $cards[3][1] === 3.0);
        $this->actingAs($this->admin)->get(route('dashboards.growth', $this->filters(['unit' => $this->unit->id])))
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
    }

    public function test_growth_ratio_uses_totals_and_metrics_do_not_duplicate_campaigns(): void
    {
        $campaign = $this->campaign([$this->unit->id, $this->sibling->id]);
        CampaignMetric::create(['campaign_id' => $campaign->id, 'metric_date' => '2026-09-10', 'spend' => 100, 'attributed_revenue' => 100, 'roas' => 90]);
        CampaignMetric::create(['campaign_id' => $campaign->id, 'metric_date' => '2026-09-11', 'spend' => 300, 'attributed_revenue' => 700, 'roas' => 90]);
        CampaignMetric::create(['campaign_id' => $campaign->id, 'metric_date' => '2026-08-11', 'spend' => 9999, 'attributed_revenue' => 9999]);
        $this->actingAs($this->admin)->get(route('dashboards.growth', $this->filters()))->assertOk()
            ->assertViewHas('cards', fn ($cards) => $cards[0][1] === 1 && (float) $cards[1][1] === 400.0 && (float) $cards[2][1] === 800.0 && $cards[3][1] === 2.0);
    }

    public function test_missing_metrics_and_zero_spend_show_no_roas(): void
    {
        $campaign = $this->campaign();
        $this->actingAs($this->admin)->get(route('dashboards.growth', $this->filters()))
            ->assertViewHas('cards', fn ($cards) => $cards[1][1] === null && $cards[3][1] === null);
        CampaignMetric::create(['campaign_id' => $campaign->id, 'metric_date' => '2026-09-10', 'spend' => 0, 'attributed_revenue' => 10]);
        $this->get(route('dashboards.growth', $this->filters()))->assertViewHas('cards', fn ($cards) => $cards[3][1] === null);
    }

    public function test_validation_rejects_bad_dates_ranges_and_scope_input(): void
    {
        $this->actingAs($this->admin);
        $this->getJson(route('dashboards.operations', $this->filters(['from' => 'bad', 'to' => '2026-02-30'])))->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(route('dashboards.hr', $this->filters(['from' => '2026-09-30', 'to' => '2026-09-01'])))->assertUnprocessable();
        $this->getJson(route('dashboards.growth', $this->filters(['from' => '2020-01-01'])))->assertUnprocessable();
        $this->getJson(route('dashboards.operations', $this->filters(['unit' => ['bad'], 'organization_id' => $this->org->id, 'operational_unit_id' => $this->unit->id, 'page' => -1])))
            ->assertUnprocessable()->assertJsonValidationErrors(['unit', 'organization_id', 'operational_unit_id', 'page']);
    }

    public function test_expired_future_foreign_roles_and_inactive_units_do_not_authorize(): void
    {
        $user = $this->user('BOOTH_SUPERVISOR', $this->unit->id);
        $role = $user->roles->first();
        $user->roles()->updateExistingPivot($role->id, ['ends_at' => now()->subMinute()]);
        $this->actingAs($user)->get(route('dashboards.operations'))->assertForbidden();
        $user->roles()->updateExistingPivot($role->id, ['ends_at' => null, 'starts_at' => now()->addDay()]);
        $this->get(route('dashboards.operations'))->assertForbidden();
        $user->roles()->updateExistingPivot($role->id, ['starts_at' => null]);
        $this->unit->update(['status' => 'INACTIVE']);
        $this->get(route('dashboards.operations'))->assertForbidden();
        $this->org->update(['status' => 'INACTIVE']);
        $this->actingAs($this->admin)->get(route('dashboards.growth'))->assertForbidden();
    }

    public function test_pagination_preserves_filters_and_output_is_escaped(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->sale(['number' => 'FIND-'.$i]);
        }
        $this->actingAs($this->admin)->get(route('dashboards.operations', $this->filters(['search' => 'FIND', 'unit' => $this->unit->id])))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 16 && $rows->count() === 15)->assertSee('page=2')->assertSee('search=FIND');
        $this->unit->update(['name' => '<script>alert(1)</script>']);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
