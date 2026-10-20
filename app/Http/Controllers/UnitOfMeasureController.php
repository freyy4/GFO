<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexUomRequest;
use App\Http\Requests\StoreUomRequest;
use App\Http\Requests\UpdateUomRequest;
use App\Models\AuditLog;
use App\Models\UnitOfMeasure;
use App\Support\UomUsage;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UnitOfMeasureController extends Controller
{
    public function index(IndexUomRequest $request): View
    {
        $filters = $request->validated();
        $search = $filters['search'] ?? '';
        $uoms = UomUsage::query($filters['usage'] ?? null)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when(isset($filters['dimension']), fn ($query) => $query->where('dimension', $filters['dimension']))
            ->orderBy('code')->orderBy('id')->paginate(15)->withQueryString();
        $dimensions = UnitOfMeasure::whereNotNull('dimension')->distinct()->orderBy('dimension')->pluck('dimension');

        $canManage = Gate::allows('create', UnitOfMeasure::class);

        return view('uoms.index', compact('uoms', 'dimensions', 'filters', 'canManage'));
    }

    public function create(): View
    {
        Gate::authorize('create', UnitOfMeasure::class);

        return view('uoms.create', ['uom' => new UnitOfMeasure(['decimal_places' => 2]), 'inUse' => false]);
    }

    public function store(StoreUomRequest $request): RedirectResponse
    {
        try {
            $uom = DB::transaction(function () use ($request) {
                Gate::authorize('create', UnitOfMeasure::class);
                $uom = UnitOfMeasure::create($request->validated());
                $this->audit($request, $uom, 'create', [], $uom->toArray());

                return $uom;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['code' => 'Kode satuan sudah digunakan.']);
        }

        return to_route('uoms.show', $uom)->with('success', 'Satuan berhasil ditambahkan ke katalog global.');
    }

    public function show(UnitOfMeasure $uom): View
    {
        Gate::authorize('view', $uom);

        return view('uoms.show', ['uom' => UomUsage::query()->findOrFail($uom->id), 'canManage' => Gate::allows('create', UnitOfMeasure::class)]);
    }

    public function edit(UnitOfMeasure $uom): View
    {
        Gate::authorize('update', $uom);
        $uom = UomUsage::query()->findOrFail($uom->id);

        return view('uoms.edit', ['uom' => $uom, 'inUse' => (bool) $uom->is_used]);
    }

    public function update(UpdateUomRequest $request, UnitOfMeasure $uom): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $uom) {
                $uom = UnitOfMeasure::lockForUpdate()->findOrFail($uom->id);
                Gate::authorize('update', $uom);
                $this->ensureUnused($uom);
                $before = $uom->toArray();
                $uom->update($request->validated());
                $this->audit($request, $uom, 'update', $before, $uom->toArray());
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['code' => 'Kode satuan sudah digunakan.']);
        }

        return to_route('uoms.show', $uom)->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Request $request, UnitOfMeasure $uom): RedirectResponse
    {
        Gate::authorize('delete', $uom);
        DB::transaction(function () use ($request, $uom) {
            $uom = UnitOfMeasure::lockForUpdate()->findOrFail($uom->id);
            Gate::authorize('delete', $uom);
            $this->ensureUnused($uom);
            $before = $uom->toArray();
            try {
                $uom->delete();
            } catch (QueryException $exception) {
                if (in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23503'], true)) {
                    throw ValidationException::withMessages(['uom' => 'Satuan masih digunakan dan tidak dapat dihapus.']);
                }
                throw $exception;
            }
            $this->audit($request, $uom, 'delete', $before, []);
        }, 3);

        return to_route('uoms.index')->with('success', 'Satuan berhasil dihapus.');
    }

    private function ensureUnused(UnitOfMeasure $uom): void
    {
        if (UomUsage::lockedInUse($uom)) {
            throw ValidationException::withMessages(['uom' => 'Satuan telah digunakan. Definisi tidak dapat diubah atau dihapus; buat satuan baru bila diperlukan.']);
        }
    }

    private function audit(Request $request, UnitOfMeasure $uom, string $event, array $before, array $after): void
    {
        AuditLog::create([
            'organization_id' => $request->user()->organization_id, 'user_id' => $request->user()->id,
            'event' => 'uoms.'.$event, 'auditable_type' => $uom->getMorphClass(), 'auditable_id' => $uom->id,
            'old_values' => $before, 'new_values' => $after,
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
