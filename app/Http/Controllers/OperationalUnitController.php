<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexOperationalUnitRequest;
use App\Http\Requests\StoreOperationalUnitRequest;
use App\Http\Requests\UpdateOperationalUnitRequest;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\OperationalUnit;
use App\Models\Organization;
use App\Policies\OperationalUnitAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OperationalUnitController extends Controller
{
    public function index(IndexOperationalUnitRequest $request): View
    {
        $filters = $request->validated();
        $search = $filters['search'] ?? '';
        $units = OperationalUnitAccess::query($request->user())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')->orWhere('city', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('operational-units.index', compact('units', 'filters'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', OperationalUnit::class);

        return $this->form($request, new OperationalUnit, 'create');
    }

    public function store(StoreOperationalUnitRequest $request): RedirectResponse
    {
        $unit = DB::transaction(function () use ($request) {
            $this->lockOrganization($request);
            Gate::authorize('create', OperationalUnit::class);
            $unit = new OperationalUnit;
            $this->save($request, $unit, $request->validated());
            $this->audit($request, $unit, 'create', []);

            return $unit;
        });

        return to_route('operational-units.show', $unit)->with('success', 'Unit operasional berhasil dibuat.');
    }

    public function show(Request $request, OperationalUnit $operationalUnit): View
    {
        Gate::authorize('view', $operationalUnit);
        $brand = Brand::where('organization_id', $request->user()->organization_id)->find($operationalUnit->brand_id);
        $parent = OperationalUnitAccess::query($request->user())->find($operationalUnit->parent_id);

        return view('operational-units.show', ['unit' => $operationalUnit, 'brand' => $brand, 'parent' => $parent]);
    }

    public function edit(Request $request, OperationalUnit $operationalUnit): View
    {
        Gate::authorize('update', $operationalUnit);

        return $this->form($request, $operationalUnit, 'edit');
    }

    public function update(UpdateOperationalUnitRequest $request, OperationalUnit $operationalUnit): RedirectResponse
    {
        DB::transaction(function () use ($request, $operationalUnit) {
            $this->lockOrganization($request);
            $unit = OperationalUnitAccess::query($request->user())->lockForUpdate()->findOrFail($operationalUnit->id);
            Gate::authorize('update', $unit);
            $before = $unit->toArray();
            $this->save($request, $unit, $request->validated());
            $this->audit($request, $unit, 'update', $before);
        });

        return to_route('operational-units.show', $operationalUnit)->with('success', 'Unit operasional berhasil diperbarui.');
    }

    public function destroy(Request $request, OperationalUnit $operationalUnit): RedirectResponse
    {
        DB::transaction(function () use ($request, $operationalUnit) {
            $this->lockOrganization($request);
            $unit = OperationalUnitAccess::query($request->user())->lockForUpdate()->findOrFail($operationalUnit->id);
            Gate::authorize('delete', $unit);
            if ($unit->children()->exists()) {
                throw ValidationException::withMessages(['unit' => 'Unit masih memiliki anak. Pindahkan atau arsipkan anak terlebih dahulu.']);
            }
            $before = $unit->toArray();
            $unit->update(['status' => 'INACTIVE']);
            $unit->delete();
            $this->audit($request, $unit, 'archive', $before);
        });

        return to_route('operational-units.index')->with('success', 'Unit operasional berhasil diarsipkan.');
    }

    private function form(Request $request, OperationalUnit $unit, string $page): View
    {
        return view('operational-units.'.$page, [
            'unit' => $unit,
            'brands' => Brand::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(),
            'parents' => OperationalUnitAccess::query($request->user())->when($unit->exists, fn ($query) => $query->where('id', '!=', $unit->id))->orderBy('name')->get(),
        ]);
    }

    private function lockOrganization(Request $request): void
    {
        Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
    }

    private function save(Request $request, OperationalUnit $unit, array $data): void
    {
        if (OperationalUnit::withTrashed()->where('organization_id', $request->user()->organization_id)
            ->where('code', $data['code'])->when($unit->exists, fn ($query) => $query->where('id', '!=', $unit->id))->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode sudah digunakan dalam organization ini.']);
        }
        if (! empty($data['brand_id']) && ! Brand::where('organization_id', $request->user()->organization_id)->lockForUpdate()->find($data['brand_id'])) {
            throw ValidationException::withMessages(['brand_id' => 'Brand tidak tersedia.']);
        }
        $parentId = $data['parent_id'] ?? null;
        if ($parentId && (! $unit->exists || (int) $unit->parent_id !== (int) $parentId)
            && ! OperationalUnitAccess::query($request->user())->whereKey($parentId)->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Unit induk tidak tersedia atau tidak dapat diakses.']);
        }
        $visited = $unit->exists ? [$unit->id] : [];
        while ($parentId) {
            if (in_array((int) $parentId, $visited, true)) {
                throw ValidationException::withMessages(['parent_id' => 'Unit induk tidak boleh membentuk siklus.']);
            }
            $visited[] = (int) $parentId;
            $parent = OperationalUnit::where('organization_id', $request->user()->organization_id)->find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Hierarki induk tidak valid.']);
            }
            $parentId = $parent->parent_id;
        }
        $times = ['opens_at' => $data['opens_at'] ?? null, 'closes_at' => $data['closes_at'] ?? null];
        unset($data['opens_at'], $data['closes_at']);
        $data['settings'] = isset($data['settings']) ? json_decode($data['settings']) : null;
        $unit->fill($data);
        $unit->organization_id = $request->user()->organization_id;
        // Existing datetime casts must not add a date to the database TIME columns.
        $unit->setRawAttributes(array_replace($unit->getAttributes(), $times));
        $unit->save();
    }

    private function audit(Request $request, OperationalUnit $unit, string $event, array $before): void
    {
        AuditLog::create([
            'organization_id' => $unit->organization_id, 'user_id' => $request->user()->id,
            'event' => 'operational_units.'.$event, 'auditable_type' => $unit->getMorphClass(), 'auditable_id' => $unit->id,
            'old_values' => $before, 'new_values' => $unit->fresh()->toArray(),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
