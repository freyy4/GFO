<?php

namespace App\Http\Controllers;

use App\Http\Requests\TradeRequest;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\ProductVariant;
use App\Models\RegisterSession;
use App\Models\SalesChannel;
use App\Models\SalesOrder;
use App\Support\TradeAccess;
use App\Support\TradeDefinition;
use App\Support\TradeMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TradeController extends Controller
{
    public function index(TradeRequest $request): View
    {
        $m = $request->module();
        $filters = $request->validated();
        $q = TradeAccess::query($request->user(), $m);
        if (! empty($filters['unit_id'])) {
            abort_unless(TradeAccess::units($request->user(), $m)->whereKey($filters['unit_id'])->exists(), 403);
            match ($m) {
                'stock-transfers' => $q->where(fn ($q) => $q->where('source_unit_id', $filters['unit_id'])->orWhere('destination_unit_id', $filters['unit_id'])),
                'register-sessions' => $q->whereHas('cashRegister', fn ($q) => $q->where('operational_unit_id', $filters['unit_id'])),
                'sales-refunds','sales-payments' => $q->whereHas('salesOrder', fn ($q) => $q->where('operational_unit_id', $filters['unit_id'])),
                'inventory-batches' => $q->whereHas('inventoryBalances', fn ($q) => $q->where('operational_unit_id', $filters['unit_id'])),
                'customers' => $q, default => $q->where('operational_unit_id', $filters['unit_id'])
            };
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            match ($m) {
                'customers','cash-registers' => $q->where('name', 'like', $search),
                'sales-orders','sales-refunds','stock-transfers','stock-counts' => $q->where('number', 'like', $search),
                'inventory-batches' => $q->where('batch_no', 'like', $search),
                'inventory','inventory-balances','inventory-movements','inventory-reservations','waste-records' => $q->whereHas('inventoryItem', fn ($q) => $q->withTrashed()->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('sku', 'like', $search))),
                'sales-payments','payment-settlements' => $q->where('reference_no', 'like', $search),
                'register-sessions' => $q->whereHas('cashRegister', fn ($q) => $q->where('name', 'like', $search)),
                default => $q->where('id', is_numeric($filters['search']) ? $filters['search'] : 0)
            };
        }
        if (! empty($filters['status'])) {
            if ($m === 'cash-registers') {
                $q->where('is_active', $filters['status'] === 'ACTIVE');
            } elseif ($m === 'inventory-batches') {
                $q->where('qc_status', $filters['status']);
            } elseif (! in_array($m, ['inventory', 'inventory-balances', 'inventory-movements', 'customers'], true)) {
                $q->where('status', $filters['status']);
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (! empty($filters[$key])) {
                $q->whereDate('created_at', $op, $filters[$key]);
            }
        }
        $summary = $m === 'inventory' ? ['items' => (clone $q)->distinct()->count('inventory_item_id'), 'units' => (clone $q)->distinct()->count('operational_unit_id')] : null;
        $records = $q->orderByDesc('id')->paginate(15)->withQueryString();

        return view('trade.index', $this->viewData($request, $m) + compact('records', 'filters', 'summary'));
    }

    public function create(TradeRequest $request): View
    {
        $m = $request->module();
        Gate::authorize('trade-create', $m);
        $class = TradeDefinition::MODELS[$m];
        $record = new $class;

        return view('trade.create', $this->viewData($request, $m) + ['record' => $record, 'lines' => []]);
    }

    public function store(TradeRequest $request, TradeMutation $service): RedirectResponse
    {
        $r = $service->save($request);

        return to_route($request->module().'.show', $r)->with('success', 'Data berhasil disimpan.');
    }

    public function show(TradeRequest $request, Model $tradeRecord): View
    {
        $m = $request->module();
        $relation = TradeDefinition::relation($m);

        return view('trade.show', $this->viewData($request, $m) + ['record' => $tradeRecord, 'rows' => $relation ? $tradeRecord->$relation()->get() : collect()]);
    }

    public function edit(TradeRequest $request, Model $tradeRecord): View
    {
        $m = $request->module();
        Gate::authorize('trade-action', [$m, $tradeRecord, 'update']);
        $record = $tradeRecord;
        $record->setAttribute('unit_id', $m === 'stock-transfers' ? $record->source_unit_id : $record->operational_unit_id);
        if ($m === 'stock-transfers') {
            $record->setAttribute('destination_id', $record->destination_unit_id);
        }
        if ($m === 'daily-closings') {
            $actuals = $record->dailyClosingPayments()->pluck('actual_amount', 'payment_method');
            foreach (['card_actual' => 'CARD', 'qris_actual' => 'QRIS', 'transfer_actual' => 'TRANSFER'] as $key => $method) {
                $record->setAttribute($key, $actuals[$method] ?? '0');
            }
        }
        $rel = TradeDefinition::relation($m);
        $lines = $rel ? $record->$rel()->get()->map(fn ($l) => array_intersect_key($l->attributesToArray(), TradeDefinition::lineFields($m)))->all() : [];

        return view('trade.edit', $this->viewData($request, $m) + compact('record', 'lines'));
    }

    public function update(TradeRequest $request, Model $tradeRecord, TradeMutation $service): RedirectResponse
    {
        $r = $service->save($request, $tradeRecord);

        return to_route($request->module().'.show', $r)->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, Model $tradeRecord, TradeMutation $service): RedirectResponse
    {
        $m = $request->route()->defaults['module'];
        $service->action($request, $m, $tradeRecord, 'delete');

        return to_route($m.'.index')->with('success', 'Draft/data berhasil dihapus.');
    }

    public function action(TradeRequest $request, Model $tradeRecord, TradeMutation $service): RedirectResponse
    {
        $service->action($request, $request->module(), $tradeRecord, $request->route()->defaults['operation'], $request->validated());

        return to_route($request->module().'.show', $tradeRecord)->with('success', 'Tindakan berhasil diproses.');
    }

    private function viewData(Request $request, string $m): array
    {
        $u = $request->user();
        $units = TradeAccess::units($u, $m);
        $options = [];
        $options['unit_id'] = $units->orderBy('name')->pluck('name', 'id')->all();
        $options['destination_id'] = $options['unit_id'];
        $options['operational_unit_id'] = $options['unit_id'];
        $options['source_unit_id'] = $options['unit_id'];
        $options['destination_unit_id'] = $options['unit_id'];
        $items = TradeAccess::items($u)->with('baseUom')->get();
        $options['inventory_item_id'] = $items->mapWithKeys(fn ($i) => [$i->id => $i->sku.' · '.$i->name.' ('.$i->baseUom->code.')'])->all();
        $options['inventory_batch_id'] = InventoryBatch::whereIn('inventory_item_id', $items->pluck('id'))->get()->mapWithKeys(fn ($b) => [$b->id => ($options['inventory_item_id'][$b->inventory_item_id] ?? '').' · '.$b->batch_no.' · '.($b->expires_on?->format('Y-m-d') ?? 'tanpa kedaluwarsa')])->all();
        $options['direction'] = ['IN' => 'Tambah stok (penyesuaian)', 'OUT' => 'Kurangi stok (penyesuaian)'];
        $options['payment_method'] = ['CASH' => 'Tunai', 'CARD' => 'Kartu', 'QRIS' => 'QRIS', 'TRANSFER' => 'Transfer bank'];
        if (! TradeDefinition::inventory($m)) {
            $options['customer_id'] = Customer::where('organization_id', $u->organization_id)->pluck('name', 'id')->all();
            $registers = CashRegister::whereIn('operational_unit_id', TradeAccess::units($u, $m)->select('id'))->get();
            $options['cash_register_id'] = $registers->mapWithKeys(fn ($r) => [$r->id => ($options['unit_id'][$r->operational_unit_id] ?? '').' · '.$r->name])->all();
            $options['register_session_id'] = RegisterSession::whereIn('cash_register_id', $registers->pluck('id'))->where('status', 'OPEN')->where('opened_by', $u->id)->get()->mapWithKeys(fn ($s) => [$s->id => '#'.$s->id.' · '.$options['cash_register_id'][$s->cash_register_id]])->all();
            $options['sales_channel_id'] = SalesChannel::where('organization_id', $u->organization_id)->where('is_active', true)->when($m === 'sales-orders', fn ($q) => $q->where('type', 'POS'))->pluck('name', 'id')->all();
            $options['sales_order_id'] = SalesOrder::where('organization_id', $u->organization_id)->whereIn('operational_unit_id', TradeAccess::units($u, $m)->select('id'))->whereIn('status', ['COMPLETED', 'PARTIALLY_REFUNDED'])->pluck('number', 'id')->all();
            $options['product_variant_id'] = ProductVariant::where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true)->whereHas('brand', fn ($q) => $q->where('organization_id', $u->organization_id))->whereIn('brand_id', TradeAccess::units($u, $m)->select('brand_id')))->get()->mapWithKeys(fn ($v) => [$v->id => $v->sku.' · '.$v->name.' · harga dasar '.$v->selling_price])->all();
        }

        return ['module' => $m, 'label' => TradeDefinition::LABELS[$m], 'fields' => TradeDefinition::fields($m), 'lineFields' => TradeDefinition::lineFields($m), 'actions' => TradeDefinition::actions($m), 'options' => $options, 'currency' => $u->organization->currency];
    }
}
