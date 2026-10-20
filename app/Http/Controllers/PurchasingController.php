<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchasingActionRequest;
use App\Http\Requests\PurchasingFilterRequest;
use App\Http\Requests\PurchasingSaveRequest;
use App\Models\InventoryBalance;
use App\Models\PurchaseOrderLine;
use App\Support\PurchasingAccess;
use App\Support\PurchasingDecimal;
use App\Support\PurchasingDefinition;
use App\Support\PurchasingMutation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchasingController extends Controller
{
    public function index(PurchasingFilterRequest $request): View
    {
        $module = $request->module();
        $filters = $request->validated();
        if ($module === 'purchase-requirements') {
            return $this->requirements($request, $filters);
        }
        $q = PurchasingAccess::query($request->user(), $module);
        if ($module === 'purchase-orders') {
            $q->withTrashed();
            match ($filters['archived'] ?? 'no') {
                'yes' => $q->onlyTrashed(), 'all' => $q, default => $q->withoutTrashed()
            };
        }
        if (! empty($filters['destination_unit_id']) && PurchasingDefinition::document($module)) {
            $q->where('destination_unit_id', $filters['destination_unit_id']);
        }
        if (! empty($filters['supplier_id']) && $module !== 'purchase-requisitions') {
            $module === 'supplier-price-history' ? $q->whereHas('supplierItem', fn ($q) => $q->where('supplier_id', $filters['supplier_id'])) : $q->where('supplier_id', $filters['supplier_id']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            if (PurchasingDefinition::document($module)) {
                $q->where('number', 'like', $search);
            } elseif ($module === 'supplier-contracts') {
                $q->where('contract_no', 'like', $search);
            } else {
                $relation = $module === 'supplier-items' ? 'inventoryItem' : 'supplierItem.inventoryItem';
                $q->whereHas($relation, fn ($q) => $q->withTrashed()->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('sku', 'like', $search)));
            }
        }
        if (! empty($filters['status'])) {
            if ($module === 'supplier-items') {
                $q->where('is_active', $filters['status'] === 'ACTIVE');
            } elseif ($module === 'supplier-price-history') {
                $today = now(PurchasingMutation::timezone($request))->format('Y-m-d');
                match ($filters['status']) {
                    'FUTURE' => $q->whereDate('effective_from', '>', $today),
                    'EXPIRED' => $q->whereDate('effective_until', '<', $today),
                    'CURRENT' => $q->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today)),
                    default => $q->whereRaw('1=0'),
                };
            } else {
                $q->where('status', $filters['status']);
            }
        }
        $date = match ($module) {
            'purchase-requisitions' => 'required_at', 'purchase-orders' => 'order_date', 'goods-receipts' => 'received_at', 'supplier-contracts' => 'starts_on', 'supplier-price-history' => 'effective_from', default => 'created_at'
        };
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                if (in_array($date, ['required_at', 'received_at', 'created_at'], true)) {
                    $bound = CarbonImmutable::parse($filters[$key], PurchasingMutation::timezone($request));
                    $key === 'from' ? $q->where($date, '>=', $bound->startOfDay()->utc()) : $q->where($date, '<', $bound->addDay()->startOfDay()->utc());
                } else {
                    $q->whereDate($date, $operator, $filters[$key]);
                }
            }
        }
        $records = $q->with($this->relations($module))->orderByDesc('id')->paginate(15)->withQueryString();

        return view('purchasing.index', $this->viewData($request, $module) + compact('filters', 'records'));
    }

    private function requirements(PurchasingFilterRequest $request, array $filters): View
    {
        $data = $this->viewData($request, 'purchase-requirements');
        $unit = ! empty($filters['destination_unit_id']) ? PurchasingAccess::units($request->user(), 'purchase-requirements')->findOrFail($filters['destination_unit_id']) : null;
        $items = null;
        if ($unit) {
            $q = PurchasingAccess::items($request->user(), 'purchase-requirements')->where(fn ($q) => $q->whereNull('brand_id')->orWhere('brand_id', $unit->brand_id));
            if (! empty($filters['search'])) {
                $q->where(fn ($q) => $q->where('sku', 'like', '%'.$filters['search'].'%')->orWhere('name', 'like', '%'.$filters['search'].'%'));
            }
            $q->select('inventory_items.*')->selectSub(InventoryBalance::where('operational_unit_id', $unit->id)->whereColumn('inventory_item_id', 'inventory_items.id')->selectRaw('COALESCE(SUM(on_hand - reserved - quarantined), 0)'), 'available_snapshot')
                ->selectSub(PurchaseOrderLine::whereColumn('inventory_item_id', 'inventory_items.id')->whereColumn('uom_id', 'inventory_items.base_uom_id')
                    ->whereHas('purchaseOrder', fn ($q) => $q->where('organization_id', $request->user()->organization_id)->where('destination_unit_id', $unit->id)->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED']))
                    ->selectRaw('COALESCE(SUM(ordered_quantity - received_quantity), 0)'), 'on_order');
            $items = $q->with('baseUom')->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
            foreach ($items as $item) {
                $available = PurchasingDecimal::max('0', (string) $item->available_snapshot);
                $target = PurchasingDecimal::max($item->reorder_point, $item->safety_stock);
                $item->setAttribute('suggested_quantity', PurchasingDecimal::max('0', bcsub(bcsub($target, $available, 4), (string) $item->on_order, 4)));
            }
        }

        return view('purchasing.requirements', $data + compact('filters', 'unit', 'items'));
    }

    public function create(PurchasingFilterRequest $request): View
    {
        $module = $request->module();
        Gate::authorize('purchasing-create', $module);
        $class = PurchasingDefinition::MODELS[$module];
        $filters = $request->validated();
        $record = new $class(['destination_unit_id' => $filters['destination_unit_id'] ?? null, 'supplier_id' => $filters['supplier_id'] ?? null,
            'purchase_order_id' => $filters['purchase_order_id'] ?? null, 'currency' => $request->user()->organization->currency,
            'tax_amount' => '0.00', 'discount_amount' => '0.00', 'minimum_order_quantity' => '0', 'lead_time_hours' => 0]);
        $lines = [];
        if ($module === 'purchase-requisitions' && ! empty($filters['inventory_item_id'])) {
            $lines = [['inventory_item_id' => $filters['inventory_item_id'], 'required_quantity' => '']];
        }
        if ($module === 'goods-receipts' && $record->purchase_order_id) {
            $po = PurchasingAccess::orders($request->user(), $module)->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->findOrFail($record->purchase_order_id);
            $lines = $po->purchaseOrderLines()->whereColumn('received_quantity', '<', 'ordered_quantity')->get()->map(fn ($line) => [
                'purchase_order_line_id' => $line->id, 'received_quantity' => bcsub($line->ordered_quantity, $line->received_quantity, 4), 'accepted_quantity' => '0', 'rejected_quantity' => '0'])->all();
        }

        return view('purchasing.create', $this->viewData($request, $module, $record) + compact('record', 'lines'));
    }

    public function store(PurchasingSaveRequest $request, PurchasingMutation $mutation): RedirectResponse
    {
        $record = $mutation->save($request);

        return to_route($request->module().'.show', $record)->with('success', 'Data purchasing berhasil disimpan.');
    }

    public function show(Request $request, Model $purchasingRecord): View
    {
        $module = $request->route()->defaults['module'];
        Gate::authorize('purchasing-view', [$module, $purchasingRecord]);
        $record = $purchasingRecord->load($this->relations($module));
        $lines = PurchasingDefinition::document($module) ? $record->{PurchasingDefinition::relation($module)}()->with(['inventoryItem' => fn ($q) => $q->withTrashed()->where('organization_id', $request->user()->organization_id), 'uom'])->orderBy('id')->get() : collect();

        return view('purchasing.show', $this->viewData($request, $module, $record) + compact('record', 'lines'));
    }

    public function edit(Request $request, Model $purchasingRecord): View
    {
        $module = $request->route()->defaults['module'];
        Gate::authorize('purchasing-action', [$module, $purchasingRecord, 'update']);
        $record = $purchasingRecord;
        $lines = PurchasingDefinition::document($module) ? $record->{PurchasingDefinition::relation($module)}()->orderBy('id')->get()->map(function ($line) use ($module) {
            $values = array_intersect_key($line->attributesToArray(), PurchasingDefinition::lineFields($module));
            foreach (PurchasingDefinition::lineFields($module) as $key => [$label, $type]) {
                if ($type === 'date') {
                    $values[$key] = $line->$key?->format('Y-m-d');
                }
            }

            return $values;
        })->all() : [];

        return view('purchasing.edit', $this->viewData($request, $module, $record) + compact('record', 'lines'));
    }

    public function update(PurchasingSaveRequest $request, Model $purchasingRecord, PurchasingMutation $mutation): RedirectResponse
    {
        $record = $mutation->save($request, $purchasingRecord);

        return to_route($request->module().'.show', $record)->with('success', 'Data purchasing berhasil diperbarui.');
    }

    public function destroy(Request $request, Model $purchasingRecord, PurchasingMutation $mutation): RedirectResponse
    {
        $module = $request->route()->defaults['module'];
        $mutation->action($request, $module, $purchasingRecord, 'delete');

        return to_route($module.'.index')->with('success', 'Data berhasil dihapus; audit tetap tersimpan.');
    }

    public function action(PurchasingActionRequest $request, Model $purchasingRecord, PurchasingMutation $mutation): RedirectResponse
    {
        $module = $request->route()->defaults['module'];
        $action = $request->route()->defaults['action'];
        $mutation->action($request, $module, $purchasingRecord, $action, $request->validated());

        return to_route($module.'.show', $purchasingRecord)->with('success', 'Tindakan purchasing berhasil diproses.');
    }

    private function relations(string $module): array
    {
        return match ($module) {
            'purchase-requisitions' => ['destinationUnit' => fn ($q) => $q->withTrashed()],
            'purchase-orders', 'goods-receipts' => ['supplier' => fn ($q) => $q->withTrashed(), 'destinationUnit' => fn ($q) => $q->withTrashed()],
            'supplier-contracts' => ['supplier' => fn ($q) => $q->withTrashed()],
            'supplier-items' => ['supplier' => fn ($q) => $q->withTrashed(), 'inventoryItem' => fn ($q) => $q->withTrashed(), 'purchaseUom'],
            'supplier-price-history' => ['supplierItem.supplier' => fn ($q) => $q->withTrashed(), 'supplierItem.inventoryItem' => fn ($q) => $q->withTrashed(), 'supplierItem.purchaseUom'],
        };
    }

    private function viewData(Request $request, string $module, ?Model $record = null): array
    {
        $user = $request->user();
        $options = [];
        $options['destination_unit_id'] = PurchasingAccess::units($user, $module)->orderBy('name')->pluck('name', 'id')->all();
        $options['supplier_id'] = PurchasingAccess::suppliers($user)->orderBy('name')->get()->mapWithKeys(fn ($supplier) => [$supplier->id => $supplier->name.' · '.$supplier->approval_status])->all();
        $options['inventory_item_id'] = PurchasingAccess::items($user, $module)->with('baseUom')->orderBy('name')->get()
            ->mapWithKeys(fn ($item) => [$item->id => $item->sku.' · '.$item->name.' ('.$item->baseUom->code.')'])->all();
        if ($module === 'purchase-orders') {
            $options['purchase_requisition_id'] = PurchasingAccess::requisitions($user, $module)->where('status', 'APPROVED')->with('destinationUnit')->get()
                ->mapWithKeys(fn ($pr) => [$pr->id => $pr->number.' · '.$pr->destinationUnit->name])->all();
        }
        if ($module === 'goods-receipts') {
            $orders = PurchasingAccess::orders($user, $module)->where(fn ($q) => $q->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->orWhere('id', $record?->purchase_order_id ?? 0))->with(['purchaseOrderLines.inventoryItem' => fn ($q) => $q->withTrashed()->where('organization_id', $user->organization_id), 'purchaseOrderLines.uom'])->get();
            $options['purchase_order_id'] = $orders->pluck('number', 'id')->all();
            $options['purchase_order_line_id'] = $orders->flatMap(fn ($po) => $po->purchaseOrderLines->filter(fn ($line) => $line->inventoryItem !== null)->map(fn ($line) => ['id' => $line->id, 'label' => $po->number.' · '.$line->inventoryItem->name.' · sisa '.bcsub($line->ordered_quantity, $line->received_quantity, 4).' '.$line->uom->code]))->pluck('label', 'id')->all();
        }
        if ($module === 'supplier-price-history') {
            $options['supplier_item_id'] = PurchasingAccess::supplierItems($user, $module)->with(['supplier' => fn ($q) => $q->withTrashed(), 'inventoryItem' => fn ($q) => $q->withTrashed(), 'purchaseUom'])->get()
                ->mapWithKeys(fn ($item) => [$item->id => $item->supplier->name.' · '.$item->inventoryItem->name.' ('.$item->purchaseUom->code.')'])->all();
        }
        $actions = match ($module) {
            'purchase-requisitions','purchase-orders' => ['submit' => 'Ajukan', 'approve' => 'Setujui', 'reject' => 'Tolak', 'cancel' => 'Batalkan'],
            'goods-receipts' => ['confirm' => 'Konfirmasi QC & penerimaan', 'cancel' => 'Batalkan'],
            'supplier-contracts','supplier-items' => ['activate' => 'Aktifkan', 'deactivate' => 'Nonaktifkan'],
            'supplier-price-history' => ['close' => 'Tutup periode harga'], default => [],
        };

        return ['module' => $module, 'label' => PurchasingDefinition::LABELS[$module], 'options' => $options, 'actions' => $actions,
            'fields' => $module === 'purchase-requirements' ? [] : PurchasingDefinition::fields($module), 'lineFields' => PurchasingDefinition::lineFields($module),
            'timezone' => PurchasingMutation::timezone($request)];
    }
}
