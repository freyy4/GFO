<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\AuditLog;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Brand::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'])],
            'business_model' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $base = Brand::withTrashed()->where('organization_id', $request->user()->organization_id);
        $counts = [
            'all' => (clone $base)->count(),
            'active' => (clone $base)->whereNull('deleted_at')->where('status', 'ACTIVE')->count(),
            'draft' => (clone $base)->whereNull('deleted_at')->where('status', 'DRAFT')->count(),
        ];
        $businessModels = (clone $base)->distinct()->orderBy('business_model')->pluck('business_model');
        $brands = (clone $base)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['business_model'] ?? null, fn ($query, $model) => $query->where('business_model', $model))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('brands.index', compact('brands', 'counts', 'businessModels', 'filters'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Brand::class);

        return view('brands.create', ['brand' => new Brand, 'organization' => $request->user()->organization]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $brand = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['settings'] = isset($data['settings']) ? json_decode($data['settings']) : null;
            $brand = Brand::create([...$data, 'status' => 'DRAFT']);
            $this->audit($request, $brand, 'create', []);

            return $brand;
        });

        return to_route('brands.show', $brand)->with('success', 'Brand berhasil dibuat sebagai DRAFT.');
    }

    public function show(Brand $brand): View
    {
        Gate::authorize('view', $brand);
        $brand->load('organization')->loadCount(['operationalUnits' => fn ($query) => $query->where('organization_id', $brand->organization_id)]);

        return view('brands.show', compact('brand'));
    }

    public function edit(Brand $brand): View
    {
        Gate::authorize('update', $brand);

        return view('brands.edit', ['brand' => $brand, 'organization' => $brand->organization]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        DB::transaction(function () use ($request, $brand) {
            $brand = $this->lockedBrand($request, $brand);
            Gate::authorize('update', $brand);
            $before = $brand->toArray();
            $data = $request->validated();
            $data['settings'] = isset($data['settings']) ? json_decode($data['settings']) : null;
            $brand->update($data);
            $this->audit($request, $brand, 'update', $before);
        });

        return to_route('brands.show', $brand)->with('success', 'Brand berhasil diperbarui.');
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        return $this->transition($request, $brand, 'delete');
    }

    public function restore(Request $request, Brand $brand): RedirectResponse
    {
        return $this->transition($request, $brand, 'restore');
    }

    public function activate(Request $request, Brand $brand): RedirectResponse
    {
        return $this->transition($request, $brand, 'activate');
    }

    public function deactivate(Request $request, Brand $brand): RedirectResponse
    {
        return $this->transition($request, $brand, 'deactivate');
    }

    private function transition(Request $request, Brand $brand, string $action): RedirectResponse
    {
        DB::transaction(function () use ($request, $brand, $action) {
            $brand = $this->lockedBrand($request, $brand);
            Gate::authorize($action, $brand);
            $before = $brand->toArray();
            switch ($action) {
                case 'activate':
                    Validator::make($brand->toArray(), [
                        'code' => ['required'], 'name' => ['required'], 'business_model' => ['required'],
                        'inventory_policy' => ['required', Rule::in(['FEFO', 'FIFO'])], 'costing_method' => ['required'],
                    ], ['required' => ':attribute wajib diisi sebelum aktivasi.'])->validate();
                    $brand->update(['status' => 'ACTIVE', 'activated_at' => now()]);
                    break;
                case 'deactivate':
                    $brand->update(['status' => 'INACTIVE']);
                    break;
                case 'delete':
                    $brand->update(['status' => 'ARCHIVED']);
                    $brand->delete();
                    break;
                case 'restore':
                    $brand->status = $brand->activated_at ? 'INACTIVE' : 'DRAFT';
                    $brand->restore();
                    break;
            }
            $this->audit($request, $brand, $action, $before);
        });
        $messages = ['delete' => 'Brand berhasil diarsipkan.', 'restore' => 'Brand berhasil dipulihkan. Aktivasi dilakukan terpisah.',
            'activate' => 'Brand berhasil diaktifkan.', 'deactivate' => 'Brand berhasil dinonaktifkan.'];

        return to_route('brands.index')->with('success', $messages[$action]);
    }

    private function lockedBrand(Request $request, Brand $brand): Brand
    {
        return Brand::withTrashed()->where('organization_id', $request->user()->organization_id)
            ->lockForUpdate()->findOrFail($brand->id);
    }

    private function audit(Request $request, Brand $brand, string $event, array $before): void
    {
        AuditLog::create([
            'organization_id' => $brand->organization_id, 'user_id' => $request->user()->id,
            'event' => $event, 'auditable_type' => $brand->getMorphClass(), 'auditable_id' => $brand->id,
            'old_values' => $before, 'new_values' => $brand->fresh()->toArray(),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
