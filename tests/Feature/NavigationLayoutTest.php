<?php

namespace Tests\Feature;

use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NavigationLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(?string $role = null): User
    {
        $organization = Organization::create(['code' => 'ORG-'.Organization::count(), 'name' => 'Garuta Test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'status' => 'ACTIVE']);
        if ($role) {
            $this->role($user, $role);
        }

        return $user;
    }

    private function role(User $user, string $code, array $pivot = []): Role
    {
        $role = Role::create(['organization_id' => $user->organization_id, 'code' => $code, 'name' => $code, 'scope_type' => 'ORGANIZATION']);
        $user->roles()->attach($role, $pivot);

        return $role;
    }

    public function test_dashboard_requires_login_and_public_pages_have_no_admin_shell(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        foreach (['/', route('login')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('id="admin-sidebar"', false)->assertDontSee('class="admin-topbar"', false);
        }
    }

    public function test_super_admin_sees_every_domain_and_missing_routes_are_disabled(): void
    {
        $this->actingAs($this->user('SUPER_ADMIN'))->get(route('dashboard'))
            ->assertOk()->assertSee('Unit dalam hasil')->assertSee('Segera')->assertSee('aria-disabled="true"', false)
            ->assertViewHas('navigation', fn ($data) => count($data['groups']) === 15);
        $data = app(Navigation::class)->data();
        foreach ($data['groups'] as $group) {
            foreach ($group['children'] as $item) {
                if (! Route::has($item['route'])) {
                    $this->assertFalse($item['enabled']);
                    $this->assertNull($item['url']);
                }
            }
        }
        $this->assertSame('Dashboard', $data['breadcrumbs'][0]['label']);
    }

    public function test_each_prepared_role_receives_only_relevant_groups(): void
    {
        $expectations = [
            'DIRECTOR' => ['Keuangan', 'SDM & Operasional'],
            'OPERATIONS_MANAGER' => ['Inventory', 'Payroll & Performance'],
            'KITCHEN_MANAGER' => ['Produksi', 'Keuangan'],
            'WAREHOUSE_MANAGER' => ['Purchasing', 'Payroll & Performance'],
            'BOOTH_SUPERVISOR' => ['Penjualan & POS', 'Keuangan'],
            'CREW' => ['SDM & Operasional', 'Keuangan'],
            'FINANCE' => ['Keuangan', 'Produksi'],
            'HR' => ['Payroll & Performance', 'Inventory'],
            'PROCUREMENT' => ['Purchasing', 'Produksi'],
            'MAINTENANCE' => ['Asset & Maintenance', 'Keuangan'],
            'MARKETING' => ['Marketing & Growth', 'Inventory'],
            'AUDITOR' => ['Laporan', 'Penjualan & POS'],
        ];
        foreach ($expectations as $role => [$included, $excluded]) {
            $this->actingAs($this->user($role))->get(route('dashboard'))->assertOk()
                ->assertViewHas('navigation', function ($data) use ($included, $excluded) {
                    $groups = array_column($data['groups'], 'label');

                    return in_array($included, $groups, true) && ! in_array($excluded, $groups, true);
                });
        }
    }

    public function test_missing_organization_and_unit_use_safe_fallbacks_and_escape_names(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>', 'status' => 'ACTIVE']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Organization belum tersedia')->assertSee('Belum ada unit')->assertSee('Belum ada akses dashboard analitik')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertViewHas('navigation', fn ($data) => count($data['groups']) === 1 && $data['activeUnit'] === null);
    }

    public function test_expired_future_foreign_and_foreign_unit_assignments_are_ignored(): void
    {
        $user = $this->user();
        $other = $this->user('SUPER_ADMIN');
        $user->roles()->attach($other->roles()->first());
        $this->role($user, 'FINANCE', ['ends_at' => now()->subDay()]);
        $this->role($user, 'HR', ['starts_at' => now()->addDay()]);
        $unit = OperationalUnit::create(['organization_id' => $other->organization_id, 'code' => 'FOREIGN', 'name' => 'Secret Unit', 'type' => 'BOOTH']);
        $this->role($user, 'OPERATIONS_MANAGER', ['operational_unit_id' => $unit->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('navigation', fn ($data) => count($data['groups']) === 1)
            ->assertDontSee('Secret Unit');
    }

    public function test_permission_menu_and_unit_picker_use_existing_scoped_access(): void
    {
        $user = $this->user();
        $unit = OperationalUnit::create(['organization_id' => $user->organization_id, 'code' => 'LOCAL', 'name' => 'Allowed Unit', 'type' => 'BOOTH']);
        $sibling = OperationalUnit::create(['organization_id' => $user->organization_id, 'code' => 'OTHER', 'name' => 'Restricted Unit', 'type' => 'WAREHOUSE']);
        $role = $this->role($user, 'CUSTOM', ['operational_unit_id' => $unit->id]);
        $permission = Permission::create(['code' => 'operational_units.view', 'name' => 'View units', 'module' => 'operational_units']);
        $role->permissions()->attach($permission);
        $this->actingAs($user)->getJson(route('dashboard', ['unit' => $sibling->id]))->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->get(route('dashboard', ['unit' => $unit->id]))->assertOk()->assertSee('Allowed Unit')->assertDontSee('Restricted Unit')
            ->assertViewHas('navigation', fn ($data) => $data['units']->count() === 1 && $data['activeUnit']->id === $unit->id);
        $this->getJson(route('dashboard', ['unit' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->get(route('operational-units.index', ['type' => 'BOOTH']))->assertOk();
        $groups = app(Navigation::class)->data()['groups'];
        $master = collect($groups)->firstWhere('label', 'Master Data');
        $this->assertTrue($master['active']);
        $item = collect($master['children'])->firstWhere('label', 'Operational Unit');
        $this->assertTrue($item['active']);
        $this->assertTrue(collect($item['children'])->firstWhere('label', 'Booth')['active']);
        $this->assertFalse(collect($item['children'])->firstWhere('label', 'Warehouse')['active']);
    }

    public function test_navigation_has_bounded_queries_and_optional_badges_do_not_query_business_tables(): void
    {
        $user = $this->user('SUPER_ADMIN');
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThan(30, count($queries));
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/from ["`](alerts|open_shifts|sales_orders)["`]/', $query['query']);
        }
        request()->attributes->remove('admin_navigation');
        request()->attributes->set('navigation_badges', ['alerts' => 3, 'open_shifts' => 2]);
        $data = app(Navigation::class)->data();
        $this->assertSame(3, collect($data['groups'])->firstWhere('label', 'Risk & Alert')['count']);
        $hr = collect($data['groups'])->firstWhere('label', 'SDM & Operasional');
        $this->assertSame(2, collect($hr['children'])->firstWhere('label', 'Open Shift')['count']);
    }
}
