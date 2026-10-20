<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RegisterSession;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\TradeDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradeManagementTest extends TestCase
{
    use RefreshDatabase;

    private $org;

    private $user;

    private $unit;

    private $destination;

    private $item;

    private $variant;

    private $register;

    private $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->org = Organization::create(['code' => 'GFO', 'name' => 'Garuta', 'timezone' => 'UTC']);
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'is_system' => true, 'scope_type' => 'ORGANIZATION']);
        $this->user->roles()->attach($role);
        $brand = Brand::create(['organization_id' => $this->org->id, 'code' => 'BR', 'name' => 'Brand', 'status' => 'ACTIVE']);
        $this->unit = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $brand->id, 'code' => 'U1', 'name' => 'Unit One', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $this->destination = OperationalUnit::create(['organization_id' => $this->org->id, 'brand_id' => $brand->id, 'code' => 'U2', 'name' => 'Unit Two', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $uom = UnitOfMeasure::create(['code' => 'PCS', 'name' => 'Pieces', 'decimal_places' => 0]);
        $this->item = InventoryItem::create(['organization_id' => $this->org->id, 'brand_id' => $brand->id, 'base_uom_id' => $uom->id, 'sku' => 'ITEM', 'name' => 'Stock Item', 'item_type' => 'FINISHED_GOOD', 'tracking_policy' => 'NONE', 'standard_cost' => '3']);
        $product = Product::create(['brand_id' => $brand->id, 'code' => 'PRODUCT', 'name' => 'Product', 'is_active' => true]);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'inventory_item_id' => $this->item->id, 'sku' => 'SKU', 'name' => 'Variant', 'selling_price' => '10.25', 'is_active' => true]);
        $this->register = CashRegister::create(['operational_unit_id' => $this->unit->id, 'code' => 'POS', 'name' => 'POS One']);
        $this->session = RegisterSession::create(['cash_register_id' => $this->register->id, 'opened_by' => $this->user->id, 'opened_at' => now(), 'opening_cash' => 100, 'status' => 'OPEN']);
        $this->actingAs($this->user);
    }

    private function store($m, $data)
    {
        if ($m === 'inventory-movements') {
            $data['submission_token'] ??= (string) Str::uuid();
        }
        $this->post(route($m.'.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $class = TradeDefinition::MODELS[$m];

        return $class::latest('id')->firstOrFail();
    }

    private function stock($qty = '20')
    {
        return $this->store('inventory-movements', ['unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'quantity' => $qty, 'direction' => 'IN', 'reason' => 'Opening stock']);
    }

    private function order($qty = '2')
    {
        return $this->store('sales-orders', ['register_session_id' => $this->session->id, 'lines' => [['product_variant_id' => $this->variant->id, 'quantity' => $qty]]]);
    }

    private function pay($order): void
    {
        $this->patch(route('sales-orders.checkout', $order), ['payment_method' => 'CASH', 'tendered_amount' => $order->total_amount])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_all_index_and_available_create_pages_render(): void
    {
        foreach (TradeDefinition::MODELS as $m => $class) {
            $this->get(route($m.'.index'))->assertOk();
            if (! TradeDefinition::readOnly($m)) {
                $this->get(route($m.'.create'))->assertOk();
            }
        }
        $this->get(route('pos.index'))->assertOk();
    }

    public function test_checkout_posts_payment_and_inventory_exactly_once(): void
    {
        $this->stock();
        $order = $this->order();
        $this->get(route('sales-orders.show', $order))->assertOk();
        $this->get(route('sales-orders.edit', $order))->assertOk();
        $this->assertSame('20.5000', bcadd($order->total_amount, '0', 4));
        $this->pay($order);
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame('18.0000', InventoryBalance::first()->on_hand);
        $this->assertSame('6.00', $order->fresh()->cogs_amount);
        $this->assertDatabaseCount('sales_payments', 1);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->patch(route('sales-orders.checkout', $order), ['payment_method' => 'CASH', 'tendered_amount' => '20.50'])->assertForbidden();
        $this->put(route('sales-orders.update', $order), [])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'sales_orders.checkout']);
    }

    public function test_insufficient_stock_rolls_back_payment_and_all_lines(): void
    {
        $this->stock('1');
        $order = $this->order('2');
        $this->patch(route('sales-orders.checkout', $order), ['payment_method' => 'CASH', 'tendered_amount' => '20.50'])->assertSessionHasErrors('record');
        $this->assertSame('1.0000', InventoryBalance::first()->on_hand);
        $this->assertSame('DRAFT', $order->fresh()->status);
        $this->assertDatabaseCount('sales_payments', 0);
    }

    public function test_reservations_release_and_waste_approval(): void
    {
        $this->stock('10');
        $r = $this->store('inventory-reservations', ['unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'quantity' => '8']);
        $order = $this->order('3');
        $this->patch(route('sales-orders.checkout', $order), ['payment_method' => 'CASH', 'tendered_amount' => $order->total_amount])->assertSessionHasErrors();
        $this->patch(route('inventory-reservations.release', $r))->assertSessionHasNoErrors();
        $this->pay($order);
        $w = $this->store('waste-records', ['unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'quantity' => '2', 'reason_code' => 'DAMAGED', 'notes' => 'Damaged stock']);
        $this->patch(route('waste-records.approve', $w))->assertSessionHasNoErrors();
        $this->assertSame('5.0000', InventoryBalance::first()->on_hand);
        $this->patch(route('waste-records.approve', $w))->assertForbidden();
    }

    public function test_transfer_dispatch_and_receive_move_stock_through_transit(): void
    {
        $this->stock('10');
        $r = $this->store('stock-transfers', ['unit_id' => $this->unit->id, 'destination_id' => $this->destination->id, 'lines' => [['inventory_item_id' => $this->item->id, 'requested_quantity' => '4']]]);
        $this->get(route('stock-transfers.show', $r))->assertOk();
        $this->get(route('stock-transfers.edit', $r))->assertOk();
        $this->patch(route('stock-transfers.dispatch', $r))->assertSessionHasNoErrors();
        $target = InventoryBalance::where('operational_unit_id', $this->destination->id)->firstOrFail();
        $this->assertSame('4.0000', $target->in_transit);
        $this->assertSame('0.0000', $target->on_hand);
        $this->patch(route('stock-transfers.receive', $r))->assertSessionHasNoErrors();
        $this->assertSame('4.0000', $target->fresh()->on_hand);
        $this->assertSame('0.0000', $target->fresh()->in_transit);
        $this->patch(route('stock-transfers.receive', $r))->assertForbidden();
    }

    public function test_stock_count_requires_fresh_snapshot_and_posts_variance(): void
    {
        $this->stock('10');
        $data = ['unit_id' => $this->unit->id, 'lines' => [['inventory_item_id' => $this->item->id, 'physical_quantity' => '8', 'reason' => 'Counted']]];
        $r = $this->store('stock-counts', $data);
        $this->stock('1');
        $this->patch(route('stock-counts.approve', $r))->assertSessionHasErrors();
        $this->put(route('stock-counts.update', $r), $data)->assertSessionHasNoErrors();
        $this->patch(route('stock-counts.approve', $r))->assertSessionHasNoErrors();
        $this->assertSame('8.0000', InventoryBalance::first()->on_hand);
        $this->store('inventory-reconciliations', ['unit_id' => $this->unit->id, 'business_date' => now()->format('Y-m-d')]);
    }

    public function test_refund_is_capped_and_does_not_restock(): void
    {
        $this->stock();
        $order = $this->order();
        $this->pay($order);
        $r = $this->store('sales-refunds', ['sales_order_id' => $order->id, 'amount' => '10', 'reason' => 'Customer complaint']);
        $this->patch(route('sales-refunds.approve', $r))->assertSessionHasNoErrors();
        $this->assertSame('PARTIALLY_REFUNDED', $order->fresh()->status);
        $this->assertSame('18.0000', InventoryBalance::first()->on_hand);
        $this->post(route('sales-refunds.store'), ['sales_order_id' => $order->id, 'amount' => '11', 'reason' => 'Too much'])->assertSessionHasErrors('amount');
        $this->patch(route('sales-refunds.approve', $r))->assertForbidden();
    }

    public function test_session_close_daily_closing_and_settlement(): void
    {
        $this->stock();
        $order = $this->order();
        $this->pay($order);
        $this->patch(route('register-sessions.close', $this->session), ['closing_cash' => '120.50'])->assertSessionHasNoErrors();
        $closing = $this->store('daily-closings', ['unit_id' => $this->unit->id, 'business_date' => now()->format('Y-m-d'), 'actual_cash' => '20.50', 'card_actual' => '0', 'qris_actual' => '0', 'transfer_actual' => '0']);
        $this->patch(route('daily-closings.approve', $closing))->assertSessionHasNoErrors();
        $this->assertSame('20.50', $closing->fresh()->expected_cash);
        $settle = $this->store('payment-settlements', ['unit_id' => $this->unit->id, 'payment_method' => 'CASH', 'period_start' => now()->format('Y-m-d'), 'period_end' => now()->format('Y-m-d'), 'settled_amount' => '20.50', 'fee_amount' => '0', 'reference_no' => 'BANK1']);
        $this->patch(route('payment-settlements.settle', $settle))->assertSessionHasNoErrors();
        $this->assertSame('SETTLED', $settle->fresh()->status);
        $this->post(route('register-sessions.store'), ['cash_register_id' => $this->register->id, 'opening_cash' => '0'])->assertSessionHasErrors();
    }

    private function reader(string $m, array $abilities = ['view'], ?int $unit = null): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'R'.$user->id, 'name' => 'Role', 'scope_type' => $unit ? 'OPERATIONAL_UNIT' : 'ORGANIZATION']);
        foreach ($abilities as $ability) {
            $p = Permission::firstOrCreate(['code' => str_replace('-', '_', $m).'.'.$ability], ['name' => $ability, 'module' => $m]);
            $role->permissions()->attach($p);
        }
        $user->roles()->attach($role, ['operational_unit_id' => $unit]);

        return $user;
    }

    public function test_every_module_denies_guests_and_users_without_permissions(): void
    {
        auth()->logout();
        foreach (array_keys(TradeDefinition::MODELS) as $m) {
            $this->get(route($m.'.index'))->assertRedirect(route('login'));
        }
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($user);
        foreach (array_keys(TradeDefinition::MODELS) as $m) {
            $this->get(route($m.'.index'))->assertForbidden();
        }
    }

    public function test_read_only_permissions_do_not_allow_mutation(): void
    {
        $this->stock();
        $order = $this->order();
        $this->actingAs($this->reader('sales-orders'));
        $this->get(route('sales-orders.show', $order))->assertOk();
        $this->get(route('sales-orders.create'))->assertForbidden();
        $this->put(route('sales-orders.update', $order), [])->assertForbidden();
        $this->delete(route('sales-orders.destroy', $order))->assertForbidden();
        $this->patch(route('sales-orders.checkout', $order), [])->assertForbidden();
    }

    public function test_unit_scoping_prevents_binding_and_forged_unit_writes(): void
    {
        $move = $this->stock();
        $this->actingAs($this->reader('inventory-movements', ['view', 'create'], $this->destination->id));
        $this->get(route('inventory-movements.show', $move))->assertNotFound();
        $this->get(route('inventory-movements.index'))->assertViewHas('records', fn ($r) => $r->total() === 0);
        $this->post(route('inventory-movements.store'), ['submission_token' => (string) Str::uuid(), 'unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'direction' => 'IN', 'quantity' => '1', 'reason' => 'Forged'])->assertSessionHasErrors('unit_id');
        $this->get(route('inventory-movements.index', ['unit_id' => $this->unit->id]))->assertForbidden();
    }

    public function test_cross_organization_documents_and_references_are_inaccessible(): void
    {
        $order = $this->order();
        $foreign = Organization::create(['code' => 'FOREIGN', 'name' => 'Other']);
        $order->update(['organization_id' => $foreign->id]);
        foreach (['show', 'edit'] as $action) {
            $this->get(route('sales-orders.'.$action, $order))->assertNotFound();
        }
        $this->patch(route('sales-orders.checkout', $order), [])->assertNotFound();
        $this->delete(route('sales-orders.destroy', $order))->assertNotFound();
        $this->item->update(['organization_id' => $foreign->id]);
        $this->post(route('inventory-movements.store'), ['submission_token' => (string) Str::uuid(), 'unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'direction' => 'IN', 'quantity' => '1', 'reason' => 'Forged'])->assertSessionHasErrors('inventory_item_id');
    }

    public function test_server_fields_price_injection_and_duplicate_lines_are_rejected(): void
    {
        $line = ['product_variant_id' => $this->variant->id, 'quantity' => '1'];
        $this->post(route('sales-orders.store'), ['register_session_id' => $this->session->id, 'organization_id' => null, 'operational_unit_id' => $this->unit->id, 'total_amount' => '0', 'lines' => [$line + ['unit_price' => '0']]])->assertSessionHasErrors(['organization_id', 'operational_unit_id', 'total_amount', 'lines.0']);
        $this->post(route('sales-orders.store'), ['register_session_id' => $this->session->id, 'lines' => [$line, $line]])->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_another_cashiers_session_and_duplicate_open_session_are_rejected(): void
    {
        $this->post(route('register-sessions.store'), ['cash_register_id' => $this->register->id, 'opening_cash' => '0'])->assertSessionHasErrors();
        $this->session->update(['opened_by' => User::factory()->create(['organization_id' => $this->org->id])->id]);
        $this->post(route('sales-orders.store'), ['register_session_id' => $this->session->id, 'lines' => [['product_variant_id' => $this->variant->id, 'quantity' => '1']]])->assertSessionHasErrors('register_session_id');
    }

    public function test_price_changes_require_draft_refresh_and_cannot_charge_old_price(): void
    {
        $this->stock();
        $order = $this->order();
        $this->variant->update(['selling_price' => '20']);
        $this->patch(route('sales-orders.checkout', $order), ['payment_method' => 'CASH', 'tendered_amount' => '100'])->assertSessionHasErrors();
        $this->put(route('sales-orders.update', $order), ['register_session_id' => $this->session->id, 'lines' => [['product_variant_id' => $this->variant->id, 'quantity' => '2']]])->assertSessionHasNoErrors();
        $this->pay($order->fresh());
        $this->assertSame('40.00', $order->fresh()->total_amount);
    }

    public function test_fefo_skips_expired_batches_and_uses_earliest_expiry(): void
    {
        $this->item->update(['tracking_policy' => 'LOT_EXPIRY', 'issue_policy' => 'FEFO']);
        $batches = [];
        foreach (['expired' => now()->subDay(), 'late' => now()->addMonths(2), 'early' => now()->addMonth()] as $name => $date) {
            $batch = InventoryBatch::create(['inventory_item_id' => $this->item->id, 'batch_no' => $name, 'expires_on' => $date, 'received_on' => now()->subDays(5), 'unit_cost' => '4', 'qc_status' => 'RELEASED']);
            InventoryBalance::create(['operational_unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'inventory_batch_id' => $batch->id, 'on_hand' => '5']);
            $batches[$name] = $batch;
        }
        $order = $this->order('2');
        $this->pay($order);
        $movement = InventoryMovement::where('movement_type', 'SALE')->firstOrFail();
        $this->assertSame($batches['early']->id, $movement->inventory_batch_id);
        $this->assertSame('8.00', $order->fresh()->cogs_amount);
    }

    public function test_audit_failure_rolls_back_stock_payment_and_document_status(): void
    {
        $this->stock();
        $order = $this->order();
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->patch(route('sales-orders.checkout', $order), ['payment_method' => 'CASH', 'tendered_amount' => $order->total_amount])->assertStatus(500);
            $this->assertSame('20.0000', InventoryBalance::first()->on_hand);
            $this->assertSame('DRAFT', $order->fresh()->status);
            $this->assertDatabaseCount('sales_payments', 0);
            $this->assertDatabaseCount('inventory_movements', 1);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_customer_and_register_crud_and_batch_immutability(): void
    {
        foreach (['customers' => ['name' => '<script>unsafe()</script>', 'email' => 'customer@example.com'], 'cash-registers' => ['unit_id' => $this->unit->id, 'code' => 'SECOND', 'name' => 'Second POS']] as $m => $data) {
            $r = $this->store($m, $data);
            $this->get(route($m.'.show', $r))->assertOk()->assertDontSee('<script>unsafe()</script>', false);
            $this->get(route($m.'.edit', $r))->assertOk();
            $this->put(route($m.'.update', $r), $data)->assertSessionHasNoErrors();
            $this->delete(route($m.'.destroy', $r))->assertSessionHasNoErrors();
        }
        $batch = $this->store('inventory-batches', ['inventory_item_id' => $this->item->id, 'batch_no' => 'BATCH', 'received_on' => now()->format('Y-m-d'), 'unit_cost' => '3']);
        $this->get(route('inventory-batches.show', $batch))->assertOk();
        $this->put(route('inventory-batches.update', $batch), [])->assertForbidden();
    }

    public function test_filters_search_and_pagination(): void
    {
        for ($i = 0; $i < 16; $i++) {
            Customer::create(['organization_id' => $this->org->id, 'name' => 'Customer '.$i]);
        }
        $this->get(route('customers.index'))->assertViewHas('records', fn ($r) => $r->total() === 16 && $r->count() === 15);
        $this->get(route('customers.index', ['search' => 'Customer 15', 'from' => now()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]))->assertViewHas('records', fn ($r) => $r->total() === 1);
    }

    public function test_fractional_quantities_and_wrong_batch_are_rejected(): void
    {
        $this->post(route('inventory-movements.store'), ['submission_token' => (string) Str::uuid(), 'unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'direction' => 'IN', 'quantity' => '1.5', 'reason' => 'Invalid fraction'])->assertSessionHasErrors();
        $this->post(route('inventory-movements.store'), ['submission_token' => (string) Str::uuid(), 'unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'inventory_batch_id' => 999, 'direction' => 'IN', 'quantity' => '1', 'reason' => 'Invalid batch'])->assertSessionHasErrors('inventory_batch_id');
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_non_cash_payment_requires_exact_amount_and_unique_reference(): void
    {
        $this->stock();
        $one = $this->order();
        $this->patch(route('sales-orders.checkout', $one), ['payment_method' => 'QRIS', 'tendered_amount' => '25', 'reference_no' => 'PAY1'])->assertSessionHasErrors('tendered_amount');
        $this->patch(route('sales-orders.checkout', $one), ['payment_method' => 'QRIS', 'tendered_amount' => '20.50'])->assertSessionHasErrors('reference_no');
        $this->patch(route('sales-orders.checkout', $one), ['payment_method' => 'QRIS', 'tendered_amount' => '20.50', 'reference_no' => 'PAY1'])->assertSessionHasNoErrors();
        $two = $this->order();
        $this->patch(route('sales-orders.checkout', $two), ['payment_method' => 'QRIS', 'tendered_amount' => '20.50', 'reference_no' => 'PAY1'])->assertSessionHasErrors('reference_no');
    }

    public function test_csrf_and_inactive_organization(): void
    {
        $order = $this->order();
        $this->org->update(['status' => 'INACTIVE']);
        $this->get(route('sales-orders.index'))->assertForbidden();
        $this->org->update(['status' => 'ACTIVE']);
        $this->app['env'] = 'local';
        $this->patch(route('sales-orders.checkout', $order), [])->assertStatus(419);
        $this->post(route('inventory-movements.store'), ['submission_token' => (string) Str::uuid()])->assertStatus(419);
        $this->delete(route('sales-orders.destroy', $order))->assertStatus(419);
    }

    public function test_closing_preserves_actual_totals_for_each_payment_method(): void
    {
        $this->stock();
        $order = $this->order();
        $this->patch(route('sales-orders.checkout', $order), ['payment_method' => 'QRIS', 'tendered_amount' => '20.50', 'reference_no' => 'QR-CLOSING'])->assertSessionHasNoErrors();
        $this->patch(route('register-sessions.close', $this->session), ['closing_cash' => '100'])->assertSessionHasNoErrors();
        $data = ['unit_id' => $this->unit->id, 'business_date' => now()->format('Y-m-d'), 'actual_cash' => '0', 'card_actual' => '0', 'qris_actual' => '20.50', 'transfer_actual' => '0'];
        $r = $this->store('daily-closings', $data);
        $this->get(route('daily-closings.edit', $r))->assertOk()->assertSee('value="20.50"', false);
        $this->patch(route('daily-closings.approve', $r))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('daily_closing_payments', ['daily_closing_id' => $r->id, 'payment_method' => 'QRIS', 'actual_amount' => '20.50', 'variance_amount' => '0']);
        $this->assertSame('20.50', $r->fresh()->non_cash_actual);
    }

    public function test_closing_rejects_non_numeric_signed_actual_and_accepts_negative_refund_day(): void
    {
        $data = ['unit_id' => $this->unit->id, 'business_date' => now()->format('Y-m-d'), 'actual_cash' => 'bad', 'card_actual' => '0', 'qris_actual' => '0', 'transfer_actual' => '0'];
        $this->post(route('daily-closings.store'), $data)->assertSessionHasErrors('actual_cash');
        $this->stock();
        $order = $this->order();
        $this->pay($order);
        $order->update(['ordered_at' => now()->subDay()]);
        $order->salesPayments()->update(['paid_at' => now()->subDay()]);
        $refund = $this->store('sales-refunds', ['sales_order_id' => $order->id, 'amount' => '10', 'reason' => 'Refund today']);
        $this->patch(route('sales-refunds.approve', $refund))->assertSessionHasNoErrors();
        $data['actual_cash'] = '-10';
        $r = $this->store('daily-closings', $data);
        $this->assertSame('-10.00', $r->expected_cash);
    }

    public function test_waste_can_dispose_quarantined_stock_without_releasing_reserved_stock(): void
    {
        $this->stock('10');
        InventoryBalance::first()->update(['quarantined' => '5', 'reserved' => '4']);
        $w = $this->store('waste-records', ['unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'quantity' => '5', 'reason_code' => 'QC_FAIL', 'notes' => 'Quarantine disposal']);
        $this->patch(route('waste-records.approve', $w))->assertSessionHasNoErrors();
        $b = InventoryBalance::first();
        $this->assertSame('5.0000', $b->on_hand);
        $this->assertSame('0.0000', $b->quarantined);
        $this->assertSame('4.0000', $b->reserved);
    }

    public function test_duplicate_nullable_batch_balance_blocks_posting(): void
    {
        $this->stock();
        InventoryBalance::create(['operational_unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'on_hand' => '1']);
        $this->post(route('inventory-movements.store'), ['unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'quantity' => '1', 'direction' => 'OUT', 'reason' => 'Duplicate'])->assertSessionHasErrors();
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_manual_stock_post_retries_are_idempotent_and_changed_payload_is_rejected(): void
    {
        $data = ['submission_token' => (string) Str::uuid(), 'unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'quantity' => '5', 'direction' => 'IN', 'reason' => 'Opening'];
        $this->post(route('inventory-movements.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('inventory-movements.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('5.0000', InventoryBalance::first()->on_hand);
        $data['quantity'] = '6';
        $this->post(route('inventory-movements.store'), $data)->assertSessionHasErrors();
        $this->assertDatabaseCount('inventory_movements', 1);
    }
}
