<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexInventoryItemRequest;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\CatalogAccess;
use App\Support\InventoryItemUsage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function index(IndexInventoryItemRequest $request): View
    {
        $filters = $request->validated();
        $search = $filters['search'] ?? '';
        $items = CatalogAccess::items($request->user())->withTrashed()->with(['brand' => fn ($query) => $query->withTrashed(), 'baseUom'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('sku', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($filters['brand_id'] ?? null, fn ($query, $id) => $query->where('brand_id', $id))
            ->when($filters['item_type'] ?? null, fn ($query, $type) => $query->where('item_type', $type))
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'ARCHIVED') {
                    $query->whereNotNull('deleted_at');
                } else {
                    $query->whereNull('deleted_at')->where('is_active', $status === 'ACTIVE');
                }
            })->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('inventory-items.index', ['items' => $items, 'filters' => $filters,
            'brands' => CatalogAccess::brands($request->user(), 'inventory_items')->withTrashed()->orderBy('name')->get(),
            'abilities' => CatalogAccess::abilities($request->user(), 'inventory_items'), 'types' => StoreInventoryItemRequest::TYPES]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', InventoryItem::class);

        return $this->form($request, new InventoryItem(['is_active' => true, 'item_type' => 'RAW_MATERIAL', 'tracking_policy' => 'NONE',
            'issue_policy' => 'FIFO', 'reorder_point' => 0, 'safety_stock' => 0, 'standard_cost' => 0]), 'create');
    }

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        try {
            $item = DB::transaction(function () use ($request) {
                $this->lockOrganization($request);
                Gate::authorize('create', InventoryItem::class);
                $data = $request->validated();
                $item = new InventoryItem;
                $this->validateReferences($request, $item, $data);
                $item->fill($data);
                $item->organization_id = $request->user()->organization_id;
                $item->save();
                $this->audit($request, $item, 'create', []);

                return $item;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['sku' => 'SKU sudah digunakan dalam organization ini.']);
        }

        return to_route('inventory-items.show', $item)->with('success', 'Inventory item berhasil dibuat.');
    }

    public function show(Request $request, InventoryItem $inventoryItem): View
    {
        Gate::authorize('view', $inventoryItem);
        $inventoryItem->load('baseUom');

        return view('inventory-items.show', ['item' => $inventoryItem,
            'brand' => CatalogAccess::brands($request->user(), 'inventory_items')->withTrashed()->find($inventoryItem->brand_id),
            'inUse' => InventoryItemUsage::inUse($inventoryItem), 'types' => StoreInventoryItemRequest::TYPES,
            'abilities' => CatalogAccess::abilities($request->user(), 'inventory_items')]);
    }

    public function edit(Request $request, InventoryItem $inventoryItem): View
    {
        Gate::authorize('update', $inventoryItem);

        return $this->form($request, $inventoryItem, 'edit');
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $inventoryItem) {
                $this->lockOrganization($request);
                $item = CatalogAccess::items($request->user())->lockForUpdate()->findOrFail($inventoryItem->id);
                Gate::authorize('update', $item);
                $data = $request->validated();
                if (InventoryItemUsage::inUse($item, true)) {
                    foreach (['sku', 'brand_id', 'base_uom_id', 'item_type', 'tracking_policy', 'issue_policy'] as $field) {
                        if ((string) ($data[$field] ?? '') !== (string) ($item->$field ?? '')) {
                            throw ValidationException::withMessages([$field => 'Identitas, satuan, dan kebijakan item yang sudah digunakan tidak dapat diubah.']);
                        }
                    }
                }
                $this->validateReferences($request, $item, $data);
                $before = $item->toArray();
                $item->update($data);
                $this->audit($request, $item, 'update', $before);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['sku' => 'SKU sudah digunakan dalam organization ini.']);
        }

        return to_route('inventory-items.show', $inventoryItem)->with('success', 'Inventory item berhasil diperbarui.');
    }

    public function destroy(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        return $this->archive($request, $inventoryItem, false);
    }

    public function restore(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        return $this->archive($request, $inventoryItem, true);
    }

    private function archive(Request $request, InventoryItem $item, bool $restore): RedirectResponse
    {
        Gate::authorize($restore ? 'restore' : 'delete', $item);
        DB::transaction(function () use ($request, $item, $restore) {
            $this->lockOrganization($request);
            $item = CatalogAccess::items($request->user())->withTrashed()->lockForUpdate()->findOrFail($item->id);
            Gate::authorize($restore ? 'restore' : 'delete', $item);
            if (! $restore && InventoryItemUsage::inUse($item, true)) {
                throw ValidationException::withMessages(['item' => 'Item telah digunakan pada data operasional. Nonaktifkan item melalui halaman edit; riwayat tetap dipertahankan.']);
            }
            $before = $item->toArray();
            $item->is_active = false;
            if ($restore) {
                $item->restore();
            } else {
                $item->save();
                $item->delete();
            }
            $this->audit($request, $item, $restore ? 'restore' : 'archive', $before);
        }, 3);

        return to_route('inventory-items.index')->with('success', $restore ? 'Inventory item dipulihkan dalam status nonaktif.' : 'Inventory item berhasil diarsipkan.');
    }

    private function form(Request $request, InventoryItem $item, string $page): View
    {
        return view('inventory-items.'.$page, ['item' => $item,
            'brands' => CatalogAccess::brands($request->user(), 'inventory_items')->withTrashed()
                ->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $item->brand_id ?? 0))->orderBy('name')->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(), 'types' => StoreInventoryItemRequest::TYPES,
            'trackingOptions' => StoreInventoryItemRequest::TRACKING, 'issueOptions' => StoreInventoryItemRequest::ISSUE,
            'inUse' => $item->exists && InventoryItemUsage::inUse($item)]);
    }

    private function validateReferences(Request $request, InventoryItem $item, array $data): void
    {
        if (InventoryItem::withTrashed()->where('organization_id', $request->user()->organization_id)->whereRaw('LOWER(sku) = ?', [strtolower($data['sku'])])
            ->when($item->exists, fn ($query) => $query->where('id', '!=', $item->id))->exists()) {
            throw ValidationException::withMessages(['sku' => 'SKU sudah digunakan dalam organization ini.']);
        }
        if (! empty($data['brand_id'])) {
            $brand = CatalogAccess::brands($request->user(), 'inventory_items')->withTrashed()
                ->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $item->brand_id ?? 0))
                ->lockForUpdate()->find($data['brand_id']);
            if (! $brand) {
                throw ValidationException::withMessages(['brand_id' => 'Brand tidak tersedia.']);
            }
        }
        if (! UnitOfMeasure::lockForUpdate()->find($data['base_uom_id'])) {
            throw ValidationException::withMessages(['base_uom_id' => 'Satuan dasar tidak tersedia.']);
        }
    }

    private function lockOrganization(Request $request): void
    {
        Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
        CatalogAccess::forget($request->user());
    }

    private function audit(Request $request, InventoryItem $item, string $event, array $before): void
    {
        AuditLog::create(['organization_id' => $request->user()->organization_id, 'user_id' => $request->user()->id,
            'event' => 'inventory_items.'.$event, 'auditable_type' => $item->getMorphClass(), 'auditable_id' => $item->id,
            'old_values' => $before, 'new_values' => $item->toArray(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
