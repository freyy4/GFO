<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\PurchasingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingManagementTest extends TestCase
{
    use RefreshDatabase;

    private $org;

    private $admin;

    private $unit;

    private $item;

    private $supplier;

    private $supplierItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->org = Organization::create(['code' => 'GFO', 'name' => 'Garuta']);
        $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'SUPER_ADMIN', 'name' => 'Admin', 'scope_type' => 'ORGANIZATION', 'is_system' => true]);
        $this->admin->roles()->attach($role);
        $this->unit = OperationalUnit::create(['organization_id' => $this->org->id, 'code' => 'U1', 'name' => 'Unit One', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $uom = UnitOfMeasure::create(['code' => 'KG', 'name' => 'Kilogram', 'decimal_places' => 4]);
        $this->item = InventoryItem::create(['organization_id' => $this->org->id, 'sku' => 'ITEM', 'name' => 'Item One', 'base_uom_id' => $uom->id, 'item_type' => 'RAW_MATERIAL', 'tracking_policy' => 'NONE', 'is_active' => true, 'reorder_point' => 20, 'safety_stock' => 10]);
        $this->supplier = Supplier::create(['organization_id' => $this->org->id, 'code' => 'SUP', 'name' => 'Supplier One', 'approval_status' => 'APPROVED']);
        $this->supplierItem = SupplierItem::create(['supplier_id' => $this->supplier->id, 'inventory_item_id' => $this->item->id, 'purchase_uom_id' => $uom->id]);
        $this->actingAs($this->admin);
    }

    private function data($module, $override = []): array
    {
        return array_replace(match ($module) {
            'purchase-requisitions' => ['destination_unit_id' => $this->unit->id, 'required_at' => '2026-10-01T10:00', 'lines' => [['inventory_item_id' => $this->item->id, 'required_quantity' => '10']]],
            'purchase-orders' => ['supplier_id' => $this->supplier->id, 'destination_unit_id' => $this->unit->id, 'order_date' => '2026-09-26', 'currency' => 'IDR', 'tax_amount' => '1.00', 'discount_amount' => '0.50', 'lines' => [['inventory_item_id' => $this->item->id, 'ordered_quantity' => '10', 'unit_price' => '2.1255']]],
            'goods-receipts' => ['purchase_order_id' => $this->approvedPo()->id, 'received_at' => '2026-09-26T10:00'],
            'supplier-contracts' => ['supplier_id' => $this->supplier->id, 'contract_no' => 'CONTRACT', 'starts_on' => '2026-09-26', 'ends_on' => '2027-10-01'],
            'supplier-items' => ['supplier_id' => $this->supplier->id, 'inventory_item_id' => $this->item->id, 'minimum_order_quantity' => '1', 'lead_time_hours' => 12],
            'supplier-price-history' => ['supplier_item_id' => $this->supplierItem->id, 'price' => '100', 'currency' => 'IDR', 'effective_from' => now()->addDays(5)->format('Y-m-d')],
        }, $override);
    }

    private function save($module, $data = null)
    {
        $this->post(route($module.'.store'), $data ?? $this->data($module))->assertRedirect()->assertSessionHasNoErrors();
        $class = PurchasingDefinition::MODELS[$module];

        return $class::latest('id')->firstOrFail();
    }

    private function approve($module, $record): void
    {
        foreach (['submit', 'approve'] as $action) {
            $this->patch(route($module.'.'.$action, $record))->assertRedirect()->assertSessionHasNoErrors();
        }
    }

    private function approvedPo()
    {
        $po = $this->save('purchase-orders');
        $this->approve('purchase-orders', $po);

        return $po->fresh();
    }

    private function receipt($po, $qty = '10', $accepted = '10', $rejected = '0')
    {
        return $this->save('goods-receipts', ['purchase_order_id' => $po->id, 'received_at' => '2026-09-26T10:00', 'lines' => [['purchase_order_line_id' => $po->purchaseOrderLines()->first()->id, 'received_quantity' => $qty, 'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected, 'rejection_reason' => $rejected !== '0' ? 'Damaged' : null]]]);
    }

    public function test_all_pages_render_and_draft_crud_is_audited(): void
    {
        $this->get(route('purchase-requirements.index'))->assertOk();
        foreach (array_keys(PurchasingDefinition::MODELS) as $module) {
            if ($module === 'supplier-items') {
                $this->supplierItem->delete();
            }
            $this->get(route($module.'.index'))->assertOk();
            $this->get(route($module.'.create'))->assertOk();
            $record = $module === 'goods-receipts' ? $this->receipt($this->approvedPo()) : $this->save($module);
            if ($module === 'supplier-items') {
                $this->supplierItem = $record;
            }
            $this->get(route($module.'.show', $record))->assertOk();
            $this->get(route($module.'.edit', $record))->assertOk();
            $this->assertDatabaseHas('audit_logs', ['event' => str_replace('-', '_', $module).'.create', 'user_id' => $this->admin->id]);
        }
    }

    public function test_requisition_order_and_receipt_workflow(): void
    {
        $pr = $this->save('purchase-requisitions');
        $this->approve('purchase-requisitions', $pr);
        $po = $this->save('purchase-orders', $this->data('purchase-orders', ['purchase_requisition_id' => $pr->id]));
        $this->assertSame('21.26', $po->subtotal);
        $this->assertSame('21.76', $po->total_amount);
        $this->approve('purchase-orders', $po);
        $this->put(route('purchase-orders.update', $po), $this->data('purchase-orders'))->assertForbidden();
        $gr = $this->receipt($po, '10', '6', '4');
        $this->patch(route('goods-receipts.confirm', $gr))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('PARTIALLY_RECEIVED', $po->fresh()->status);
        $this->assertSame('6.0000', $po->purchaseOrderLines()->first()->received_quantity);
        $this->assertSame('PARTIAL', $gr->goodsReceiptLines()->first()->qc_status);
        $this->patch(route('goods-receipts.confirm', $gr))->assertForbidden();
        $next = $this->receipt($po, '4', '4');
        $this->patch(route('goods-receipts.confirm', $next))->assertSessionHasNoErrors();
        $this->assertSame('RECEIVED', $po->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseHas('audit_logs', ['event' => 'purchase_orders.receipt_progress']);
    }

    public function test_competing_receipts_cannot_over_receive(): void
    {
        $po = $this->approvedPo();
        $one = $this->receipt($po, '7', '7');
        $two = $this->receipt($po, '7', '7');
        $this->patch(route('goods-receipts.confirm', $one))->assertSessionHasNoErrors();
        $this->patch(route('goods-receipts.confirm', $two))->assertSessionHasErrors('lines.0.received_quantity');
        $this->assertSame('DRAFT', $two->fresh()->status);
        $this->assertSame('7.0000', $po->purchaseOrderLines()->first()->received_quantity);
    }

    public function test_server_fields_and_nested_mass_assignment_are_rejected(): void
    {
        $data = $this->data('purchase-orders', ['organization_id' => null, 'status' => 'APPROVED', 'total_amount' => '1']);
        $data['lines'][0]['received_quantity'] = '10';
        $this->post(route('purchase-orders.store'), $data)->assertSessionHasErrors(['organization_id', 'status', 'total_amount', 'lines.0']);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_tenant_binding_and_foreign_references_are_blocked(): void
    {
        $po = $this->save('purchase-orders');
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $po->update(['organization_id' => $other->id]);
        foreach (['show', 'edit'] as $action) {
            $this->get(route('purchase-orders.'.$action, $po))->assertNotFound();
        }
        $this->put(route('purchase-orders.update', $po), $this->data('purchase-orders'))->assertNotFound();
        $this->delete(route('purchase-orders.destroy', $po))->assertNotFound();
        $this->patch(route('purchase-orders.submit', $po))->assertNotFound();
        $this->supplier->update(['organization_id' => $other->id]);
        $this->post(route('purchase-orders.store'), $this->data('purchase-orders'))->assertSessionHasErrors('supplier_id');
        $this->get(route('purchase-orders.index'))->assertViewHas('records', fn ($rows) => $rows->total() === 0);
    }

    public function test_price_periods_and_reference_actions(): void
    {
        $history = $this->save('supplier-price-history');
        $this->post(route('supplier-price-history.store'), $this->data('supplier-price-history'))->assertSessionHasErrors('effective_from');
        $this->delete(route('supplier-items.destroy', $this->supplierItem))->assertSessionHasErrors('record');
        $contract = $this->save('supplier-contracts');
        foreach (['activate', 'deactivate'] as $action) {
            $this->patch(route('supplier-contracts.'.$action, $contract))->assertSessionHasNoErrors();
        }
        $this->patch(route('supplier-price-history.close', $history), ['effective_until' => now()->addDays(10)->format('Y-m-d')])->assertSessionHasNoErrors();
        $this->assertNotNull($history->fresh()->effective_until);
    }

    public function test_audit_failure_rolls_back_confirmation(): void
    {
        $po = $this->approvedPo();
        $gr = $this->receipt($po);
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->patch(route('goods-receipts.confirm', $gr))->assertStatus(500);
            $this->assertSame('DRAFT', $gr->fresh()->status);
            $this->assertSame('APPROVED', $po->fresh()->status);
            $this->assertSame('0.0000', $po->purchaseOrderLines()->first()->received_quantity);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    private function reader(string $module, array $abilities = ['view'], ?int $unit = null): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'ACTIVE']);
        $role = Role::create(['organization_id' => $this->org->id, 'code' => 'ROLE'.$user->id, 'name' => 'Reader', 'scope_type' => $unit ? 'OPERATIONAL_UNIT' : 'ORGANIZATION']);
        foreach ($abilities as $ability) {
            $p = Permission::firstOrCreate(['code' => str_replace('-', '_', $module).'.'.$ability], ['name' => $ability, 'module' => $module]);
            $role->permissions()->attach($p);
        }
        $user->roles()->attach($role, ['operational_unit_id' => $unit]);

        return $user;
    }

    public function test_read_access_does_not_grant_write_or_approval(): void
    {
        $po = $this->save('purchase-orders');
        $this->actingAs($this->reader('purchase-orders'));
        $this->get(route('purchase-orders.index'))->assertOk();
        $this->get(route('purchase-orders.show', $po))->assertOk();
        $this->get(route('purchase-orders.create'))->assertForbidden();
        $this->post(route('purchase-orders.store'), $this->data('purchase-orders'))->assertForbidden();
        $this->put(route('purchase-orders.update', $po), $this->data('purchase-orders'))->assertForbidden();
        $this->delete(route('purchase-orders.destroy', $po))->assertForbidden();
        $this->patch(route('purchase-orders.submit', $po))->assertForbidden();
        $this->actingAs($this->reader('purchase-orders', ['view', 'update']));
        $this->patch(route('purchase-orders.submit', $po))->assertSessionHasNoErrors();
        $this->assertSame('SUBMITTED', $po->fresh()->status);
        $this->patch(route('purchase-orders.approve', $po))->assertForbidden();
    }

    public function test_unit_scope_applies_to_binding_filters_and_mutations(): void
    {
        $po = $this->save('purchase-orders');
        $unit = OperationalUnit::create(['organization_id' => $this->org->id, 'code' => 'U2', 'name' => 'Other unit', 'type' => 'BOOTH', 'status' => 'ACTIVE']);
        $hidden = $this->save('purchase-orders', $this->data('purchase-orders', ['destination_unit_id' => $unit->id]));
        $this->actingAs($this->reader('purchase-orders', ['view', 'create', 'update', 'approve'], $this->unit->id));
        $this->get(route('purchase-orders.index'))->assertViewHas('records', fn ($r) => $r->total() === 1);
        $this->get(route('purchase-orders.show', $hidden))->assertNotFound();
        $this->get(route('purchase-orders.index', ['destination_unit_id' => $unit->id]))->assertSessionHasErrors('destination_unit_id');
        $this->post(route('purchase-orders.store'), $this->data('purchase-orders', ['destination_unit_id' => $unit->id]))->assertSessionHasErrors('destination_unit_id');
        $this->put(route('purchase-orders.update', $po), $this->data('purchase-orders'))->assertSessionHasNoErrors();
        $this->approve('purchase-orders', $po);
    }

    public function test_draft_update_delete_and_archive_pages(): void
    {
        foreach (['purchase-requisitions', 'purchase-orders', 'supplier-contracts', 'supplier-price-history'] as $module) {
            $record = $this->save($module);
            $this->put(route($module.'.update', $record), $this->data($module))->assertSessionHasNoErrors()->assertRedirect();
            $this->delete(route($module.'.destroy', $record))->assertSessionHasNoErrors()->assertRedirect();
            if ($module === 'purchase-orders') {
                $this->assertSoftDeleted($record);
                $this->get(route($module.'.show', $record))->assertOk();
                $this->get(route($module.'.index', ['archived' => 'yes']))->assertViewHas('records', fn ($r) => $r->total() === 1);
            } else {
                $this->assertModelMissing($record);
            }
        }
    }

    public function test_search_status_date_and_pagination(): void
    {
        $prototype = $this->save('purchase-orders');
        for ($i = 0; $i < 16; $i++) {
            $row = $prototype->replicate();
            $row->number = 'FIND-'.$i;
            $row->save();
        }
        $this->get(route('purchase-orders.index'))->assertViewHas('records', fn ($r) => $r->total() === 17 && $r->count() === 15);
        $this->get(route('purchase-orders.index', ['search' => 'FIND-15', 'status' => 'DRAFT', 'from' => '2026-09-26', 'to' => '2026-09-26']))->assertViewHas('records', fn ($r) => $r->total() === 1);
        $this->get(route('purchase-orders.index', ['status' => 'APPROVED']))->assertViewHas('records', fn ($r) => $r->total() === 0);
    }

    public function test_requirements_subtract_available_and_open_orders(): void
    {
        InventoryBalance::create(['operational_unit_id' => $this->unit->id, 'inventory_item_id' => $this->item->id, 'on_hand' => 8, 'reserved' => 2, 'quarantined' => 1]);
        $this->approvedPo();
        $this->get(route('purchase-requirements.index', ['destination_unit_id' => $this->unit->id]))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->first()->suggested_quantity === '5.0000');
    }

    public function test_requisition_allocation_and_linked_document_deletion_guards(): void
    {
        $pr = $this->save('purchase-requisitions');
        $this->approve('purchase-requisitions', $pr);
        $data = $this->data('purchase-orders', ['purchase_requisition_id' => $pr->id]);
        $this->save('purchase-orders', $data);
        $this->post(route('purchase-orders.store'), $data)->assertSessionHasErrors('lines.0.ordered_quantity');
        $this->patch(route('purchase-requisitions.cancel', $pr))->assertSessionHasErrors('record');
    }

    public function test_qc_totals_and_lot_expiry_are_required_before_confirmation(): void
    {
        $po = $this->approvedPo();
        $gr = $this->receipt($po, '10', '6');
        $this->patch(route('goods-receipts.confirm', $gr))->assertSessionHasErrors('lines.0.accepted_quantity');
        $gr->goodsReceiptLines()->update(['accepted_quantity' => 10]);
        $this->item->update(['tracking_policy' => 'LOT_EXPIRY']);
        $this->patch(route('goods-receipts.confirm', $gr))->assertSessionHasErrors('lines.0.supplier_batch_no');
        $gr->goodsReceiptLines()->update(['supplier_batch_no' => 'LOT1']);
        $this->patch(route('goods-receipts.confirm', $gr))->assertSessionHasErrors('lines.0.expires_on');
        $gr->goodsReceiptLines()->update(['expires_on' => '2026-01-01']);
        $this->patch(route('goods-receipts.confirm', $gr))->assertSessionHasErrors('lines.0.expires_on');
        $gr->goodsReceiptLines()->update(['expires_on' => '2027-01-01']);
        $this->patch(route('goods-receipts.confirm', $gr))->assertSessionHasNoErrors();
    }

    public function test_validation_prevents_precision_overflow_negative_totals_and_duplicate_items(): void
    {
        $data = $this->data('purchase-orders');
        $data['discount_amount'] = '1000';
        $this->post(route('purchase-orders.store'), $data)->assertSessionHasErrors();
        $data = $this->data('purchase-orders');
        $data['lines'][] = $data['lines'][0];
        $this->post(route('purchase-orders.store'), $data)->assertSessionHasErrors('lines.0.inventory_item_id');
        $data = $this->data('purchase-orders');
        $data['lines'][0]['unit_price'] = '0.12345';
        $this->post(route('purchase-orders.store'), $data)->assertSessionHasErrors('lines.0.unit_price');
        $data = $this->data('purchase-orders');
        $data['lines'][0]['unit_price'] = '99999999999999';
        $data['lines'][0]['ordered_quantity'] = '99999999999999';
        $this->post(route('purchase-orders.store'), $data)->assertSessionHasErrors();
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_inactive_and_expired_assignments_are_denied(): void
    {
        $reader = $this->reader('purchase-orders', ['view'], $this->unit->id);
        $reader->roles()->updateExistingPivot($reader->roles()->first()->id, ['ends_at' => now()->subDay()]);
        $this->actingAs($reader)->get(route('purchase-orders.index'))->assertForbidden();
        $this->org->update(['status' => 'INACTIVE']);
        $this->actingAs($this->admin)->get(route('purchase-orders.index'))->assertForbidden();
    }

    public function test_mutations_require_csrf(): void
    {
        $po = $this->save('purchase-orders');
        $this->app['env'] = 'local';
        $this->post(route('purchase-orders.store'), $this->data('purchase-orders'))->assertStatus(419);
        $this->put(route('purchase-orders.update', $po), $this->data('purchase-orders'))->assertStatus(419);
        $this->delete(route('purchase-orders.destroy', $po))->assertStatus(419);
        $this->patch(route('purchase-orders.submit', $po))->assertStatus(419);
    }

    public function test_receipt_edit_preserves_batch_dates_and_rejects_foreign_line(): void
    {
        $po = $this->approvedPo();
        $gr = $this->receipt($po);
        $gr->goodsReceiptLines()->update(['manufactured_on' => '2026-09-01', 'expires_on' => '2027-09-01']);
        $this->get(route('goods-receipts.edit', $gr))->assertOk()->assertSee('value="2026-09-01"', false)->assertSee('value="2027-09-01"', false);
        $other = $this->approvedPo();
        $data = ['purchase_order_id' => $po->id, 'received_at' => '2026-09-26T10:00', 'lines' => [[
            'purchase_order_line_id' => $other->purchaseOrderLines()->first()->id, 'received_quantity' => '1', 'accepted_quantity' => '1', 'rejected_quantity' => '0',
        ]]];
        $this->put(route('goods-receipts.update', $gr), $data)->assertSessionHasErrors('lines.0.purchase_order_line_id');
    }

    public function test_current_price_is_visible_but_immutable_and_overlap_is_inclusive(): void
    {
        $today = now($this->org->timezone)->format('Y-m-d');
        $history = $this->save('supplier-price-history', $this->data('supplier-price-history', ['effective_from' => $today]));
        $this->get(route('supplier-price-history.index', ['status' => 'CURRENT']))->assertViewHas('records', fn ($r) => $r->total() === 1);
        $this->get(route('supplier-price-history.edit', $history))->assertForbidden();
        $this->delete(route('supplier-price-history.destroy', $history))->assertForbidden();
        $this->patch(route('supplier-price-history.close', $history), ['effective_until' => $today])->assertSessionHasNoErrors();
        $this->post(route('supplier-price-history.store'), $this->data('supplier-price-history', ['effective_from' => $today]))->assertSessionHasErrors('effective_from');
        $this->save('supplier-price-history', $this->data('supplier-price-history', ['effective_from' => now()->addDay()->format('Y-m-d')]));
    }

    public function test_malformed_cross_organization_line_does_not_disclose_item_name(): void
    {
        $pr = $this->save('purchase-requisitions');
        $other = Organization::create(['code' => 'FOREIGN', 'name' => 'Foreign']);
        $this->item->update(['organization_id' => $other->id, 'name' => 'Confidential foreign item']);
        $this->get(route('purchase-requisitions.show', $pr))->assertOk()->assertDontSee('Confidential foreign item');
        $this->patch(route('purchase-requisitions.submit', $pr))->assertSessionHasErrors('lines.0.inventory_item_id');
    }
}
