<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\OpenShift;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\PayrollPeriod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\RecipeVersion;
use App\Models\Role;
use App\Models\Roster;
use App\Models\ShiftAssignment;
use App\Models\Skill;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\OperationsDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsManagementTest extends TestCase
{
    use RefreshDatabase;

    private $org;

    private $user;

    private $unit;

    private $raw;

    private $output;

    private $version;

    private $employee;

    private $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->org = Organization::create(['code' => 'GFO', 'name' => 'Garuta', 'timezone' => 'UTC']);
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'is_system' => true, 'scope_type' => 'ORGANIZATION']);
        $this->user->roles()->attach($role);
        $brand = Brand::create(['organization_id' => $this->org->id, 'code' => 'BR', 'name' => 'Brand', 'status' => 'ACTIVE']);
        $this->unit = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $brand->id, 'code' => 'K', 'name' => 'Kitchen', 'type' => 'KITCHEN', 'status' => 'ACTIVE']);
        $uom = UnitOfMeasure::create(['code' => 'PCS', 'name' => 'Pieces', 'decimal_places' => 0]);
        foreach (['raw', 'output'] as $key) {
            $this->$key = InventoryItem::create(['organization_id' => $this->org->id, 'brand_id' => $brand->id, 'base_uom_id' => $uom->id, 'sku' => $key, 'name' => $key, 'item_type' => $key === 'raw' ? 'RAW_MATERIAL' : 'FINISHED_GOOD', 'tracking_policy' => 'NONE', 'standard_cost' => 3]);
        }
        $recipe = Recipe::create(['brand_id' => $brand->id, 'code' => 'R', 'name' => 'Recipe', 'output_item_id' => $this->output->id, 'status' => 'ACTIVE']);
        $this->version = RecipeVersion::create(['recipe_id' => $recipe->id, 'version_no' => 1, 'yield_quantity' => 1, 'yield_uom_id' => $uom->id, 'effective_from' => today()->subDay(), 'status' => 'APPROVED']);
        RecipeLine::create(['recipe_version_id' => $this->version->id, 'inventory_item_id' => $this->raw->id, 'uom_id' => $uom->id, 'quantity' => 2, 'waste_percent' => 0]);
        foreach (['employee', 'other'] as $key) {
            $this->$key = Employee::create(['organization_id' => $this->org->id, 'home_unit_id' => $this->unit->id, 'employee_no' => $key, 'full_name' => $key, 'status' => 'ACTIVE', 'joined_on' => today()->subYear()]);
        }
        $this->actingAs($this->user);
    }

    private function store($module, $data)
    {
        $this->post(route($module.'.store'), $data)->assertSessionHasNoErrors()->assertRedirect();

        return (OperationsDefinition::models()[$module])::latest('id')->firstOrFail();
    }

    private function action($module, $record, $action, $data = [])
    {
        $this->patch(route($module.'.'.$action, $record), $data)->assertSessionHasNoErrors()->assertRedirect();

        return $record->fresh();
    }

    private function plan()
    {
        return $this->store('production-plans', ['unit_id' => $this->unit->id, 'production_date' => today()->toDateString(), 'lines' => [['recipe_version_id' => $this->version->id, 'destination_unit_id' => $this->unit->id, 'target_quantity' => 5]]]);
    }

    private function shift($employee = null, $day = 2)
    {
        return $this->store('shift-assignments', ['unit_id' => $this->unit->id, 'employee_id' => ($employee ?? $this->employee)->id, 'starts_at' => today()->addDays($day)->setTime(8, 0)->format('Y-m-d\TH:i'), 'ends_at' => today()->addDays($day)->setTime(16, 0)->format('Y-m-d\TH:i')]);
    }

    public function test_all_index_and_create_pages_render(): void
    {
        foreach (OperationsDefinition::models() as $m => $class) {
            $this->get(route($m.'.index'))->assertOk();
            if ($m !== 'production-material-requirements') {
                $this->get(route($m.'.create'))->assertOk();
            }
        }
    }

    public function test_production_consumes_material_and_quarantines_output_until_qc(): void
    {
        InventoryBalance::create(['operational_unit_id' => $this->unit->id, 'inventory_item_id' => $this->raw->id, 'on_hand' => 20]);
        $plan = $this->plan();
        $this->get(route('production-plans.show', $plan))->assertOk();
        $this->get(route('production-plans.edit', $plan))->assertOk();
        $this->assertEquals(10, $plan->productionMaterialRequirements()->first()->required_quantity);
        $this->action('production-plans', $plan, 'approve');
        $batch = $this->store('production-batches', ['production_plan_line_id' => $plan->productionPlanLines()->first()->id, 'planned_quantity' => 5]);
        $this->get(route('production-batches.show', $batch))->assertOk();
        $usage = $batch->productionMaterialUsages()->first();
        $this->action('production-batches', $batch, 'start', ['usages' => [['usage_id' => $usage->id, 'actual_quantity' => 10]], 'usage_reason' => 'Pemakaian sesuai resep']);
        $this->assertEquals(10, InventoryBalance::where('inventory_item_id', $this->raw->id)->first()->on_hand);
        $this->action('production-batches', $batch, 'complete', ['actual_quantity' => 5]);
        $balance = InventoryBalance::where('inventory_item_id', $this->output->id)->first();
        $this->assertEquals(5, $balance->quarantined);
        $this->patch(route('production-batches.release', $batch))->assertSessionHasErrors();
        $this->store('production-qc', ['production_batch_id' => $batch->id, 'check_type' => 'FINAL', 'result' => 'PASS', 'notes' => 'Hasil sesuai standar']);
        $this->action('production-batches', $batch, 'release');
        $this->assertEquals(0, $balance->fresh()->quarantined);
        $this->assertSame('COMPLETED', $plan->fresh()->status);
        $this->patch(route('production-batches.release', $batch))->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertGreaterThan(0, AuditLog::count());
    }

    public function test_insufficient_stock_rolls_back_batch_start(): void
    {
        $plan = $this->plan();
        $this->action('production-plans', $plan, 'approve');
        $batch = $this->store('production-batches', ['production_plan_line_id' => $plan->productionPlanLines()->first()->id, 'planned_quantity' => 5]);
        $usage = $batch->productionMaterialUsages()->first();
        $this->patch(route('production-batches.start', $batch), ['usages' => [['usage_id' => $usage->id, 'actual_quantity' => 10]], 'usage_reason' => 'Pemakaian'])->assertSessionHasErrors();
        $this->assertSame('PLANNED', $batch->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_leave_approval_opens_replacement_and_claim_fills_it(): void
    {
        $shift = $this->shift();
        $leave = $this->store('leave-requests', ['employee_id' => $this->employee->id, 'leave_type' => 'PERSONAL', 'starts_at' => $shift->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $shift->ends_at->format('Y-m-d\TH:i'), 'reason' => 'Keperluan keluarga']);
        $this->action('leave-requests', $leave, 'approve');
        $this->assertSame('ON_LEAVE', $shift->fresh()->status);
        $open = OpenShift::firstOrFail();
        $claim = $this->store('shift-claims', ['open_shift_id' => $open->id, 'employee_id' => $this->other->id]);
        $this->action('shift-claims', $claim, 'approve');
        $this->assertSame('FILLED', $open->fresh()->status);
        $this->assertSame($this->other->id, $open->shiftAssignment->employee_id);
        $this->patch(route('shift-claims.approve', $claim))->assertForbidden();
    }

    public function test_overlapping_shift_is_rejected(): void
    {
        $shift = $this->shift();
        $this->post(route('shift-assignments.store'), ['unit_id' => $this->unit->id, 'employee_id' => $this->employee->id, 'starts_at' => $shift->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $shift->ends_at->format('Y-m-d\TH:i')])->assertSessionHasErrors();
        $this->assertDatabaseCount('shift_assignments', 1);
    }

    public function test_server_owned_fields_are_rejected(): void
    {
        $this->post(route('rosters.store'), ['unit_id' => $this->unit->id, 'period_start' => today()->toDateString(), 'period_end' => today()->addWeek()->toDateString(), 'organization_id' => $this->org->id, 'status' => 'PUBLISHED'])->assertSessionHasErrors(['organization_id', 'status']);
        $this->assertDatabaseCount('rosters', 0);
    }

    public function test_roster_publish_and_overnight_template(): void
    {
        $template = $this->store('shift-templates', ['code' => 'NIGHT', 'name' => 'Malam', 'starts_at' => '22:00', 'ends_at' => '06:00', 'break_minutes' => 30]);
        $this->assertTrue($template->crosses_midnight);
        $this->get(route('shift-templates.edit', $template))->assertOk();
        $date = today()->addDays(2)->toDateString();
        $roster = $this->store('rosters', ['unit_id' => $this->unit->id, 'period_start' => $date, 'period_end' => $date]);
        $shift = $this->store('shift-assignments', ['unit_id' => $this->unit->id, 'roster_id' => $roster->id, 'shift_template_id' => $template->id, 'employee_id' => $this->employee->id, 'starts_at' => $date.'T22:00', 'ends_at' => today()->addDays(3)->format('Y-m-d').'T06:00']);
        $this->action('rosters', $roster, 'publish');
        $this->get(route('rosters.show', $roster))->assertOk();
        $this->get(route('shift-assignments.edit', $shift))->assertForbidden();
    }

    public function test_shift_swap_updates_both_assignments_and_audits_them(): void
    {
        $from = $this->shift();
        $to = $this->shift($this->other, 3);
        $swap = $this->store('shift-swaps', ['from_assignment_id' => $from->id, 'to_employee_id' => $this->other->id, 'to_assignment_id' => $to->id, 'reason' => 'Pertukaran disepakati']);
        $this->patch(route('shift-assignments.cancel', $from))->assertSessionHasErrors();
        $this->action('shift-swaps', $swap, 'approve');
        $this->assertSame($this->other->id, $from->fresh()->employee_id);
        $this->assertSame($this->employee->id, $to->fresh()->employee_id);
        $this->assertEquals(2, AuditLog::where('event', 'shift_assignments.swap')->count());
        $this->get(route('shift-swaps.show', $swap))->assertOk();
    }

    public function test_attendance_correction_requires_another_reviewer_and_recalculates_minutes(): void
    {
        $shift = ShiftAssignment::create(['operational_unit_id' => $this->unit->id, 'employee_id' => $this->employee->id, 'starts_at' => today()->subDay()->setTime(8, 0), 'ends_at' => today()->subDay()->setTime(16, 0), 'status' => 'ASSIGNED']);
        $attendance = $this->store('attendance-records', ['shift_assignment_id' => $shift->id, 'clock_in_at' => today()->subDay()->format('Y-m-d').'T08:15', 'clock_out_at' => today()->subDay()->format('Y-m-d').'T16:00']);
        $this->assertEquals(15, $attendance->late_minutes);
        $this->assertEquals(465, $attendance->worked_minutes);
        $this->action('attendance-records', $attendance, 'approve');
        $correction = $this->store('attendance-corrections', ['attendance_record_id' => $attendance->id, 'requested_clock_in_at' => today()->subDay()->format('Y-m-d').'T08:00', 'requested_clock_out_at' => today()->subDay()->format('Y-m-d').'T16:30', 'reason' => 'Koreksi log manual']);
        $this->patch(route('attendance-corrections.approve', $correction))->assertSessionHasErrors();
        $reviewer = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $reviewer->roles()->attach($this->user->roles->first());
        $this->actingAs($reviewer);
        $this->action('attendance-corrections', $correction, 'approve');
        $this->assertEquals(0, $attendance->fresh()->late_minutes);
        $this->assertEquals(30, $attendance->fresh()->overtime_minutes);
        $this->get(route('attendance-records.show', $attendance))->assertOk();
    }

    public function test_payroll_lock_prevents_attendance_creation(): void
    {
        $shift = ShiftAssignment::create(['operational_unit_id' => $this->unit->id, 'employee_id' => $this->employee->id, 'starts_at' => today()->subDay()->setTime(8, 0), 'ends_at' => today()->subDay()->setTime(16, 0), 'status' => 'ASSIGNED']);
        PayrollPeriod::create(['organization_id' => $this->org->id, 'name' => 'Locked', 'starts_on' => today()->subDay(), 'ends_on' => today(), 'status' => 'LOCKED']);
        $this->post(route('attendance-records.store'), ['shift_assignment_id' => $shift->id, 'clock_in_at' => today()->subDay()->format('Y-m-d').'T08:00', 'clock_out_at' => today()->subDay()->format('Y-m-d').'T16:00'])->assertSessionHasErrors();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_skill_verification_and_certificate_revocation(): void
    {
        $skill = $this->store('skills', ['code' => 'COOK', 'name' => 'Memasak']);
        $es = $this->store('employee-skills', ['employee_id' => $this->employee->id, 'skill_id' => $skill->id, 'level' => 3]);
        $this->action('employee-skills', $es, 'verify');
        $this->assertSame($this->user->id, $es->fresh()->verified_by);
        $this->get(route('employee-skills.edit', $es))->assertForbidden();
        $cert = $this->store('employee-certifications', ['employee_id' => $this->employee->id, 'name' => 'Food safety', 'issued_on' => today()->toDateString(), 'expires_on' => today()->addYear()->toDateString()]);
        $this->action('employee-certifications', $cert, 'revoke');
        $this->assertSame('REVOKED', $cert->fresh()->status);
    }

    public function test_forecast_rounding_and_override_validation(): void
    {
        $product = Product::create(['brand_id' => $this->unit->brand_id, 'code' => 'FOOD', 'name' => 'Food']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'inventory_item_id' => $this->output->id, 'sku' => 'FOOD', 'name' => 'Food', 'selling_price' => 10]);
        $data = ['unit_id' => $this->unit->id, 'forecast_date' => today()->toDateString(), 'lines' => [['product_variant_id' => $variant->id, 'baseline_quantity' => 3, 'safety_percent' => 10, 'override_quantity' => 0]]];
        $this->post(route('demand-forecasts.store'), $data)->assertSessionHasErrors();
        unset($data['lines'][0]['override_quantity']);
        $forecast = $this->store('demand-forecasts', $data);
        $this->assertEquals(4, $forecast->demandForecastLines()->first()->recommended_quantity);
        $this->get(route('demand-forecasts.edit', $forecast))->assertOk();
        $this->action('demand-forecasts', $forecast, 'approve');
    }

    public function test_schedule_dependencies_and_packing_require_completed_output(): void
    {
        $plan = $this->plan();
        $this->action('production-plans', $plan, 'approve');
        $date = today()->toDateString();
        $schedule = $this->store('production-schedules', ['production_plan_id' => $plan->id, 'starts_at' => $date.'T08:00', 'ends_at' => $date.'T17:00']);
        $base = ['production_schedule_id' => $schedule->id, 'production_plan_line_id' => $plan->productionPlanLines()->first()->id, 'required_crew' => 1];
        $first = $this->store('production-tasks', $base + ['task_type' => 'PREPARATION', 'name' => 'Persiapan', 'planned_start_at' => $date.'T08:00', 'planned_end_at' => $date.'T09:00']);
        $second = $this->store('production-tasks', $base + ['task_type' => 'COOKING', 'name' => 'Masak', 'planned_start_at' => $date.'T09:00', 'planned_end_at' => $date.'T12:00', 'depends_on_task_id' => $first->id]);
        $this->patch(route('production-tasks.start', $second))->assertSessionHasErrors();
        $this->action('production-tasks', $first, 'start');
        $this->action('production-tasks', $first, 'finish');
        $this->action('production-tasks', $second, 'start');
        $packing = $this->store('packing-dispatch', $base + ['task_type' => 'PACKING', 'name' => 'Kemas', 'planned_start_at' => $date.'T12:00', 'planned_end_at' => $date.'T13:00']);
        $this->patch(route('packing-dispatch.start', $packing))->assertSessionHasErrors();
        $this->get(route('production-tasks.show', $second))->assertOk();
        $this->post(route('production-schedules.store'), ['production_plan_id' => $plan->id, 'starts_at' => $date.'T10:00', 'ends_at' => $date.'T18:00'])->assertSessionHasErrors();
    }

    public function test_cross_organization_binding_and_references_are_blocked(): void
    {
        $foreign = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $unit = OperationalUnit::create(['organization_id' => $foreign->id, 'code' => 'OTHER', 'name' => 'Foreign', 'type' => 'KITCHEN']);
        $roster = Roster::create(['operational_unit_id' => $unit->id, 'period_start' => today(), 'period_end' => today()->addWeek()]);
        $this->get(route('rosters.show', $roster))->assertNotFound();
        $this->put(route('rosters.update', $roster), [])->assertNotFound();
        $this->delete(route('rosters.destroy', $roster))->assertNotFound();
        $this->post(route('rosters.store'), ['unit_id' => $unit->id, 'period_start' => today()->toDateString(), 'period_end' => today()->addWeek()->toDateString()])->assertSessionHasErrors();
        $this->get(route('rosters.index', ['unit_id' => $unit->id]))->assertForbidden();
    }

    public function test_crew_can_only_read_own_employee_records(): void
    {
        $own = $this->shift();
        $other = $this->shift($this->other);
        $crew = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $this->employee->update(['user_id' => $crew->id]);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'CREW', 'name' => 'Crew', 'scope_type' => 'UNIT']);
        $crew->roles()->attach($role, ['operational_unit_id' => $this->unit->id]);
        $this->actingAs($crew);
        $this->get(route('shift-assignments.show', $own))->assertOk();
        $this->get(route('shift-assignments.show', $other))->assertNotFound();
        $this->get(route('shift-assignments.create'))->assertForbidden();
        $this->patch(route('shift-assignments.cancel', $own))->assertForbidden();
    }

    public function test_search_status_and_pagination(): void
    {
        for ($i = 0; $i < 17; $i++) {
            Skill::create(['organization_id' => $this->org->id, 'code' => 'S'.$i, 'name' => 'Skill '.$i]);
        }
        $this->get(route('skills.index'))->assertOk()->assertViewHas('records', fn ($r) => $r->count() === 15 && $r->total() === 17);
        $this->get(route('skills.index', ['search' => 'Skill 16']))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 1);
        $plan = $this->plan();
        $this->get(route('production-plans.index', ['status' => 'APPROVED']))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 0);
        $this->get(route('production-plans.index', ['status' => 'DRAFT']))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 1);
        $this->get(route('shift-assignments.index', ['status' => 'ASSIGNED']))->assertOk();
    }

    public function test_unit_create_permission_cannot_be_used_in_another_readable_unit(): void
    {
        $second = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $this->unit->brand_id, 'code' => 'SECOND', 'name' => 'Second', 'type' => 'KITCHEN', 'status' => 'ACTIVE']);
        $foreignEmployee = Employee::create(['organization_id' => $this->org->id, 'home_unit_id' => $second->id, 'employee_no' => 'SECOND', 'full_name' => 'Second employee', 'status' => 'ACTIVE']);
        $manager = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $read = Role::create(['organization_id' => $this->org->id, 'code' => 'HR', 'name' => 'Read HR', 'scope_type' => 'ORGANIZATION']);
        $write = Role::create(['organization_id' => $this->org->id, 'code' => 'WRITER', 'name' => 'Unit writer', 'scope_type' => 'UNIT']);
        $permission = Permission::create(['code' => 'leave_requests.create', 'name' => 'Create leave', 'module' => 'leave_requests']);
        $write->permissions()->attach($permission);
        $manager->roles()->attach($read);
        $manager->roles()->attach($write, ['operational_unit_id' => $this->unit->id]);
        $this->actingAs($manager);
        $this->post(route('leave-requests.store'), ['employee_id' => $foreignEmployee->id, 'leave_type' => 'PERSONAL', 'starts_at' => today()->addDays(2)->format('Y-m-d').'T08:00', 'ends_at' => today()->addDays(2)->format('Y-m-d').'T16:00', 'reason' => 'Tidak boleh lintas unit izin tulis'])->assertSessionHasErrors();
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_audit_failure_rolls_back_roster_write(): void
    {
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->post(route('rosters.store'), ['unit_id' => $this->unit->id, 'period_start' => today()->toDateString(), 'period_end' => today()->addDays(2)->toDateString()])->assertServerError();
            $this->assertDatabaseCount('rosters', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_claim_approval_rechecks_new_schedule_conflict(): void
    {
        $date = today()->addDays(2)->format('Y-m-d');
        $slot = $this->store('shift-assignments', ['unit_id' => $this->unit->id, 'starts_at' => $date.'T08:00', 'ends_at' => $date.'T16:00']);
        $open = $this->store('open-shifts', ['shift_assignment_id' => $slot->id, 'response_deadline_at' => $date.'T07:00']);
        $claim = $this->store('shift-claims', ['open_shift_id' => $open->id, 'employee_id' => $this->other->id]);
        $this->shift($this->other);
        $this->patch(route('shift-claims.approve', $claim))->assertSessionHasErrors();
        $this->assertSame('PENDING', $claim->fresh()->status);
        $this->assertNull($slot->fresh()->employee_id);
    }

    public function test_nested_bom_without_stock_output_and_cycle_rejection(): void
    {
        $recipe = Recipe::create(['brand_id' => $this->unit->brand_id, 'code' => 'SUB', 'name' => 'Sub recipe', 'status' => 'ACTIVE']);
        $sub = RecipeVersion::create(['recipe_id' => $recipe->id, 'version_no' => 1, 'yield_quantity' => 1, 'yield_uom_id' => $this->raw->base_uom_id, 'effective_from' => today()->subDay(), 'status' => 'APPROVED']);
        RecipeLine::create(['recipe_version_id' => $sub->id, 'inventory_item_id' => $this->raw->id, 'uom_id' => $this->raw->base_uom_id, 'quantity' => 1, 'waste_percent' => 10]);
        $line = $this->version->recipeLinesAsRecipeVersion()->first();
        $line->update(['inventory_item_id' => null, 'sub_recipe_version_id' => $sub->id]);
        $plan = $this->plan();
        $this->assertEquals(11, $plan->productionMaterialRequirements()->first()->required_quantity);
        $sub->recipeLinesAsRecipeVersion()->first()->update(['inventory_item_id' => null, 'sub_recipe_version_id' => $this->version->id]);
        $this->patch(route('production-plans.approve', $plan))->assertSessionHasErrors();
        $this->assertSame('DRAFT', $plan->fresh()->status);
    }

    public function test_guest_cannot_access_operations(): void
    {
        $plan = $this->plan();
        auth()->logout();
        $this->get(route('production-plans.show', $plan))->assertRedirect(route('login'));
        $this->get(route('rosters.index'))->assertRedirect(route('login'));
    }

    public function test_leave_on_draft_roster_allows_publication_before_claim(): void
    {
        $date = today()->addDays(2)->format('Y-m-d');
        $roster = $this->store('rosters', ['unit_id' => $this->unit->id, 'period_start' => $date, 'period_end' => $date]);
        $this->store('shift-assignments', ['unit_id' => $this->unit->id, 'roster_id' => $roster->id, 'employee_id' => $this->employee->id, 'starts_at' => $date.'T08:00', 'ends_at' => $date.'T16:00']);
        $leave = $this->store('leave-requests', ['employee_id' => $this->employee->id, 'leave_type' => 'SICK', 'starts_at' => $date.'T08:00', 'ends_at' => $date.'T16:00', 'reason' => 'Izin']);
        $this->action('leave-requests', $leave, 'approve');
        $open = OpenShift::firstOrFail();
        $this->post(route('shift-claims.store'), ['open_shift_id' => $open->id, 'employee_id' => $this->other->id])->assertSessionHasErrors();
        $this->action('rosters', $roster, 'publish');
        $claim = $this->store('shift-claims', ['open_shift_id' => $open->id, 'employee_id' => $this->other->id]);
        $this->action('shift-claims', $claim, 'approve');
    }
}
