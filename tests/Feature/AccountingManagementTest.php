<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\PayrollAllocation;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PettyCashTransaction;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesRefund;
use App\Models\User;
use App\Support\AccountingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingManagementTest extends TestCase
{
    use RefreshDatabase;

    private $org;

    private $user;

    private $reviewer;

    private $unit;

    private $employee;

    private $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->org = Organization::create(['code' => 'GFO', 'name' => 'Garuta', 'timezone' => 'Asia/Jakarta']);
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $this->reviewer = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'scope_type' => 'ORGANIZATION', 'is_system' => true]);
        $this->user->roles()->attach($role);
        $this->reviewer->roles()->attach($role);
        $brand = Brand::create(['organization_id' => $this->org->id, 'code' => 'B', 'name' => 'Brand', 'status' => 'ACTIVE']);
        $this->unit = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $brand->id, 'code' => 'U', 'name' => 'Booth', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $this->employee = Employee::create(['organization_id' => $this->org->id, 'home_unit_id' => $this->unit->id, 'employee_no' => 'E1', 'full_name' => 'Employee', 'status' => 'ACTIVE', 'base_salary' => 3000000]);
        $this->category = ExpenseCategory::create(['organization_id' => $this->org->id, 'code' => 'RENT', 'name' => 'Sewa', 'pl_group' => 'RENT', 'is_fixed_cost' => true]);
        $this->actingAs($this->user);
    }

    private function store($m, $data)
    {
        if ($m === 'petty-cash') {
            $data['submission_token'] ??= (string) Str::uuid();
        }$this->post(route($m.'.store'), $data)->assertSessionHasNoErrors()->assertRedirect();

        return (AccountingDefinition::MODELS[$m])::latest('id')->firstOrFail();
    }

    private function action($m, $r, $a, $data = [])
    {
        $this->patch(route($m.'.'.$a, $r), $data)->assertSessionHasNoErrors()->assertRedirect();

        return $r->fresh();
    }

    private function expense($amount = 100)
    {
        return $this->store('expenses', ['unit_id' => $this->unit->id, 'expense_category_id' => $this->category->id, 'expense_date' => today()->toDateString(), 'amount' => $amount, 'description' => 'Biaya operasional']);
    }

    private function approveExpense($e)
    {
        $this->action('expenses', $e, 'submit');
        $this->actingAs($this->reviewer);
        $this->action('expenses', $e, 'approve');
    }

    public function test_all_list_and_create_pages_render(): void
    {
        foreach (AccountingDefinition::LABELS as $m => $label) {
            $this->get(route($m.'.index'))->assertOk();
            if (! AccountingDefinition::readOnly($m)) {
                $this->get(route($m.'.create'))->assertOk();
            }
        }
    }

    public function test_expense_approval_payment_and_petty_cash_are_atomic_and_once(): void
    {
        $this->store('petty-cash', ['unit_id' => $this->unit->id, 'amount' => 500, 'description' => 'Kas awal']);
        $e = $this->expense();
        $this->get(route('expenses.edit', $e))->assertOk();
        $this->action('expenses', $e, 'submit');
        $this->patch(route('expenses.approve', $e))->assertSessionHasErrors();
        $this->actingAs($this->reviewer);
        $this->action('expenses', $e, 'approve');
        $this->action('expenses', $e, 'pay', ['payment_method' => 'PETTY_CASH', 'payment_reference' => 'KW-1']);
        $this->assertSame('PAID', $e->fresh()->status);
        $this->assertEquals('400.00', PettyCashTransaction::latest('id')->first()->balance_after);
        $this->patch(route('expenses.pay', $e), ['payment_method' => 'PETTY_CASH', 'payment_reference' => 'KW-1'])->assertForbidden();
        $this->assertDatabaseCount('petty_cash_transactions', 2);
        $this->get(route('expenses.show', $e))->assertOk();
    }

    public function test_insufficient_petty_cash_rolls_back_payment(): void
    {
        $e = $this->expense();
        $this->approveExpense($e);
        $this->patch(route('expenses.pay', $e), ['payment_method' => 'PETTY_CASH', 'payment_reference' => 'KW'])->assertSessionHasErrors();
        $this->assertSame('APPROVED', $e->fresh()->status);
        $this->assertDatabaseCount('petty_cash_transactions', 0);
    }

    public function test_petty_cash_submission_cannot_be_replayed(): void
    {
        $d = ['unit_id' => $this->unit->id, 'amount' => 500, 'description' => 'Setoran', 'submission_token' => (string) Str::uuid()];
        $this->store('petty-cash', $d);
        $this->post(route('petty-cash.store'), $d)->assertSessionHasErrors();
        $this->assertDatabaseCount('petty_cash_transactions', 1);
    }

    public function test_bill_payment_creates_one_linked_expense(): void
    {
        $date = today()->toDateString();
        $b = $this->store('bills', ['unit_id' => $this->unit->id, 'expense_category_id' => $this->category->id, 'bill_no' => 'INV-1', 'period_start' => $date, 'period_end' => $date, 'amount' => 100, 'due_date' => $date]);
        $this->action('bills', $b, 'pay', ['payment_method' => 'BANK_TRANSFER', 'payment_reference' => 'BANK-1']);
        $this->assertSame('PAID', $b->fresh()->status);
        $this->assertDatabaseHas('expenses', ['bill_id' => $b->id, 'status' => 'PAID', 'amount' => 100]);
        $this->patch(route('bills.pay', $b), ['payment_method' => 'BANK_TRANSFER', 'payment_reference' => 'BANK-1'])->assertForbidden();
        $this->assertDatabaseCount('expenses', 1);
        $this->get(route('bills.show', $b))->assertOk();
    }

    public function test_recurring_bill_generation_rejects_duplicate_period(): void
    {
        $start = today()->startOfMonth();
        $o = $this->store('recurring-obligations', ['unit_id' => $this->unit->id, 'expense_category_id' => $this->category->id, 'type' => 'RENT', 'billing_cycle' => 'MONTHLY', 'expected_amount' => 500, 'due_day' => 10, 'starts_on' => $start->toDateString()]);
        $this->action('recurring-obligations', $o, 'generate', ['billing_month' => $start->format('Y-m')]);
        $this->patch(route('recurring-obligations.generate', $o), ['billing_month' => $start->format('Y-m')])->assertSessionHasErrors();
        $this->assertDatabaseCount('bills', 1);
        $this->get(route('recurring-obligations.show', $o))->assertOk();
    }

    public function test_kpi_results_weighted_scores_and_review_are_immutable(): void
    {
        $k = $this->store('kpi-definitions', ['code' => 'K1', 'name' => 'Kualitas', 'weight' => 40, 'direction' => 'HIGHER_BETTER']);
        $dates = ['period_start' => today()->subMonth()->startOfMonth()->toDateString(), 'period_end' => today()->subMonth()->endOfMonth()->toDateString()];
        $t = $this->store('kpi-targets', ['kpi_definition_id' => $k->id, 'employee_id' => $this->employee->id, 'target_value' => 10] + $dates);
        $r = $this->store('kpi-results', ['target_id' => $t->id, 'actual_value' => 8, 'evidence' => 'Rekap diperiksa']);
        $this->assertEquals(80, $r->score);
        $this->patch(route('kpi-results.approve', $r))->assertSessionHasErrors();
        $this->actingAs($this->reviewer);
        $this->action('kpi-results', $r, 'approve');
        $s = $this->store('performance-scores', ['employee_id' => $this->employee->id] + $dates);
        $this->assertEquals(80, $s->total_score);
        $this->actingAs($this->user);
        $this->action('performance-scores', $s, 'approve');
        $this->get(route('performance-scores.show', $s))->assertOk();
        $this->get(route('performance-scores.edit', $s))->assertForbidden();
        $this->delete(route('kpi-targets.destroy', $t))->assertSessionHasErrors();
    }

    public function test_payroll_draft_waits_for_company_formula_and_prevents_duplicate_run(): void
    {
        $period = $this->store('payroll-periods', ['name' => 'Payroll', 'starts_on' => today()->startOfMonth()->toDateString(), 'ends_on' => today()->endOfMonth()->toDateString()]);
        $run = $this->store('payroll-runs', ['payroll_period_id' => $period->id]);
        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $period->id])->assertSessionHasErrors();
        $this->patch(route('payroll-runs.calculate', $run))->assertSessionHasErrors();
        $this->patch(route('payroll-periods.lock', $period))->assertSessionHasErrors();
        $this->assertDatabaseCount('payslips', 0);
        $this->get(route('payroll-runs.show', $run))->assertOk();
        $this->action('payroll-runs', $run, 'cancel');
        $this->store('payroll-runs', ['payroll_period_id' => $period->id]);
    }

    public function test_pl_excludes_tax_and_counts_approved_costs_once(): void
    {
        $day = today()->subDays(2);
        $o = SalesOrder::create(['organization_id' => $this->org->id, 'brand_id' => $this->unit->brand_id, 'operational_unit_id' => $this->unit->id, 'number' => 'SO-1', 'ordered_at' => $day->copy()->setTime(8, 0), 'status' => 'COMPLETED', 'subtotal' => 1000, 'total_amount' => 1100, 'tax_amount' => 100, 'cogs_amount' => 200]);
        Expense::create(['organization_id' => $this->org->id, 'operational_unit_id' => $this->unit->id, 'expense_category_id' => $this->category->id, 'number' => 'E1', 'expense_date' => $day, 'amount' => 100, 'status' => 'PAID']);
        $s = $this->store('booth-pl', ['unit_id' => $this->unit->id, 'period_type' => 'DAILY', 'period_start' => $day->toDateString(), 'period_end' => $day->toDateString()]);
        $this->assertEquals(1000, $s->revenue);
        $this->assertEquals(800, $s->gross_profit);
        $this->assertEquals(700, $s->contribution);
        $this->assertEquals(125, $s->break_even_sales);
        $this->get(route('break-even.show', $s))->assertOk();
        $this->action('booth-pl', $s, 'recalculate');
        $this->assertEquals(700, $s->fresh()->contribution);
    }

    public function test_cross_organization_records_and_forged_fields_are_blocked(): void
    {
        $foreign = Organization::create(['code' => 'F', 'name' => 'Foreign']);
        $c = ExpenseCategory::create(['organization_id' => $foreign->id, 'code' => 'C', 'name' => 'Foreign', 'pl_group' => 'RENT']);
        $this->get(route('expense-categories.show', $c))->assertNotFound();
        $this->delete(route('expense-categories.destroy', $c))->assertNotFound();
        $this->post(route('expenses.store'), ['unit_id' => $this->unit->id, 'expense_category_id' => $c->id, 'expense_date' => today()->toDateString(), 'amount' => 100, 'description' => 'test'])->assertSessionHasErrors();
        $this->post(route('expenses.store'), ['unit_id' => $this->unit->id, 'expense_category_id' => $this->category->id, 'expense_date' => today()->toDateString(), 'amount' => 100, 'description' => 'test', 'organization_id' => $foreign->id, 'status' => 'PAID'])->assertSessionHasErrors(['organization_id', 'status']);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_unit_scoped_finance_cannot_read_other_unit_or_write_without_permission(): void
    {
        $e = $this->expense();
        $second = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $this->unit->brand_id, 'code' => 'U2', 'name' => 'Other unit', 'type' => 'BOOTH']);
        $u = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'FINANCE', 'name' => 'Finance', 'scope_type' => 'UNIT']);
        $u->roles()->attach($role, ['operational_unit_id' => $second->id]);
        $this->actingAs($u);
        $this->get(route('expenses.show', $e))->assertNotFound();
        $this->get(route('expenses.create'))->assertForbidden();
        $this->get(route('expenses.index', ['unit_id' => $this->unit->id]))->assertForbidden();
        $this->get(route('finance.index'))->assertOk();
    }

    public function test_audit_failure_rolls_back_expense(): void
    {
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->post(route('expenses.store'), ['unit_id' => $this->unit->id, 'expense_category_id' => $this->category->id, 'expense_date' => today()->toDateString(), 'amount' => 100, 'description' => 'test'])->assertServerError();
            $this->assertDatabaseCount('expenses', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_search_filter_pagination_and_guest_protection(): void
    {
        for ($i = 0; $i < 16; $i++) {
            ExpenseCategory::create(['organization_id' => $this->org->id, 'code' => 'C'.$i, 'name' => 'Category '.$i, 'pl_group' => 'OTHER_OPEX']);
        }
        $this->get(route('expense-categories.index'))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 17 && $r->count() === 15);
        $this->get(route('expense-categories.index', ['search' => 'Category 15']))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 1);
        $this->expense();
        $this->get(route('expenses.index', ['status' => 'PAID']))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 0);
        auth()->logout();
        $this->get(route('finance.index'))->assertRedirect(route('login'));
    }

    public function test_weighted_kpis_lower_direction_and_approved_period_freeze(): void
    {
        $dates = ['period_start' => today()->subMonth()->startOfMonth()->toDateString(), 'period_end' => today()->subMonth()->endOfMonth()->toDateString()];
        foreach ([['A', 25, 'HIGHER_BETTER', 5], ['B', 75, 'LOWER_BETTER', 0]] as [$code,$weight,$direction,$actual]) {
            $this->actingAs($this->user);
            $k = $this->store('kpi-definitions', ['code' => $code, 'name' => $code, 'weight' => $weight, 'direction' => $direction]);
            $t = $this->store('kpi-targets', ['kpi_definition_id' => $k->id, 'employee_id' => $this->employee->id, 'target_value' => 10] + $dates);
            $r = $this->store('kpi-results', ['target_id' => $t->id, 'actual_value' => $actual, 'evidence' => 'Evidence']);
            $this->actingAs($this->reviewer);
            $this->action('kpi-results', $r, 'approve');
        }
        $score = $this->store('performance-scores', ['employee_id' => $this->employee->id] + $dates);
        $this->assertEquals(87.5, $score->total_score);
        $this->actingAs($this->user);
        $this->action('performance-scores', $score, 'approve');
        $k = $this->store('kpi-definitions', ['code' => 'C', 'name' => 'C', 'weight' => 10, 'direction' => 'HIGHER_BETTER']);
        $this->post(route('kpi-targets.store'), ['kpi_definition_id' => $k->id, 'employee_id' => $this->employee->id, 'target_value' => 10] + $dates)->assertSessionHasErrors();
    }

    public function test_pl_refund_date_and_zero_margin_break_even(): void
    {
        $day = today()->subDays(2);
        $o = SalesOrder::create(['organization_id' => $this->org->id, 'brand_id' => $this->unit->brand_id, 'operational_unit_id' => $this->unit->id, 'number' => 'REF-1', 'ordered_at' => $day->copy()->subDay()->setTime(8, 0), 'status' => 'REFUNDED', 'subtotal' => 1000, 'total_amount' => 1100, 'tax_amount' => 100, 'cogs_amount' => 200]);
        SalesRefund::create(['sales_order_id' => $o->id, 'number' => 'R1', 'amount' => 1100, 'reason' => 'Refund', 'status' => 'REFUNDED', 'refunded_at' => $day->copy()->setTime(8, 0)]);
        $s = $this->store('booth-pl', ['unit_id' => $this->unit->id, 'period_type' => 'DAILY', 'period_start' => $day->toDateString(), 'period_end' => $day->toDateString()]);
        $this->assertEquals(-1000, $s->revenue);
        $this->assertEquals(0, $s->cogs);
        $this->assertNull($s->break_even_sales);
    }

    public function test_used_category_cannot_be_reclassified_and_draft_cannot_skip_submission(): void
    {
        $e = $this->expense();
        $this->put(route('expense-categories.update', $this->category), ['code' => 'RENT', 'name' => 'Changed', 'pl_group' => 'OTHER_OPEX', 'is_fixed_cost' => 0])->assertSessionHasErrors();
        $this->actingAs($this->reviewer);
        $this->patch(route('expenses.approve', $e))->assertForbidden();
        $this->patch(route('expenses.pay', $e), ['payment_method' => 'BANK_TRANSFER', 'payment_reference' => 'X'])->assertForbidden();
    }

    public function test_payslip_and_allocation_scopes_exclude_other_organization(): void
    {
        $p = PayrollPeriod::create(['organization_id' => $this->org->id, 'name' => 'P', 'starts_on' => today()->subMonth()->startOfMonth(), 'ends_on' => today()->subMonth()->endOfMonth()]);
        $run = PayrollRun::create(['payroll_period_id' => $p->id, 'status' => 'APPROVED']);
        $slip = Payslip::create(['payroll_run_id' => $run->id, 'employee_id' => $this->employee->id, 'gross_pay' => 100, 'net_pay' => 100, 'status' => 'APPROVED']);
        $alloc = PayrollAllocation::create(['payslip_id' => $slip->id, 'operational_unit_id' => $this->unit->id, 'allocated_amount' => 100]);
        $this->get(route('payslips.show', $slip))->assertOk();
        $this->get(route('payroll-allocations.show', $alloc))->assertOk();
        $this->post('/payslips')->assertStatus(405);
        $foreign = Organization::create(['code' => 'FOREIGN', 'name' => 'Foreign']);
        $u = User::factory()->create(['organization_id' => $foreign->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $foreign->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'scope_type' => 'ORGANIZATION', 'is_system' => true]);
        $u->roles()->attach($role);
        $this->actingAs($u);
        $this->get(route('payslips.show',$slip))->assertNotFound();
        $this->get(route('payroll-allocations.show',$alloc))->assertNotFound();
    }
}
