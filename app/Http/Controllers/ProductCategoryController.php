<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProductCategoryRequest;
use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProductCategory;
use App\Support\CatalogAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(IndexProductCategoryRequest $request): View
    {
        $filters = $request->validated();
        $search = $filters['search'] ?? '';
        $categories = CatalogAccess::categories($request->user())->with(['brand' => fn ($query) => $query->withTrashed()])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%')))
            ->when($filters['brand_id'] ?? null, fn ($query, $id) => $query->where('brand_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'ACTIVE'))
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('product-categories.index', [
            'categories' => $categories, 'filters' => $filters,
            'brands' => CatalogAccess::brands($request->user(), 'product_categories')->withTrashed()->orderBy('name')->get(),
            'abilities' => CatalogAccess::abilities($request->user(), 'product_categories'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ProductCategory::class);

        return $this->form($request, new ProductCategory(['is_active' => true, 'sort_order' => 0]), 'create');
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        try {
            $category = DB::transaction(function () use ($request) {
                $this->lockOrganization($request);
                Gate::authorize('create', ProductCategory::class);
                $data = $request->validated();
                $category = new ProductCategory;
                $this->validateHierarchy($request, $category, $data);
                $category->fill($data)->save();
                $this->audit($request, $category, 'create', []);

                return $category;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['slug' => 'Slug sudah digunakan dalam brand ini.']);
        }

        return to_route('product-categories.show', $category)->with('success', 'Kategori produk berhasil dibuat.');
    }

    public function show(Request $request, ProductCategory $productCategory): View
    {
        Gate::authorize('view', $productCategory);
        $brand = CatalogAccess::brands($request->user(), 'product_categories')->withTrashed()->findOrFail($productCategory->brand_id);
        $parent = CatalogAccess::categories($request->user())->where('brand_id', $productCategory->brand_id)->find($productCategory->parent_id);

        return view('product-categories.show', ['category' => $productCategory, 'brand' => $brand, 'parent' => $parent,
            'abilities' => CatalogAccess::abilities($request->user(), 'product_categories')]);
    }

    public function edit(Request $request, ProductCategory $productCategory): View
    {
        Gate::authorize('update', $productCategory);

        return $this->form($request, $productCategory, 'edit');
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $productCategory) {
                $this->lockOrganization($request);
                $category = CatalogAccess::categories($request->user())->lockForUpdate()->findOrFail($productCategory->id);
                Gate::authorize('update', $category);
                $data = $request->validated();
                if ((int) $data['brand_id'] !== (int) $category->brand_id) {
                    throw ValidationException::withMessages(['brand_id' => 'Brand kategori tidak dapat dipindahkan. Buat kategori baru pada brand tujuan.']);
                }
                $this->validateHierarchy($request, $category, $data);
                $before = $category->toArray();
                $category->update($data);
                $this->audit($request, $category, 'update', $before);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['slug' => 'Slug sudah digunakan dalam brand ini.']);
        }

        return to_route('product-categories.show', $productCategory)->with('success', 'Kategori produk berhasil diperbarui.');
    }

    public function destroy(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        Gate::authorize('delete', $productCategory);
        DB::transaction(function () use ($request, $productCategory) {
            $this->lockOrganization($request);
            $category = CatalogAccess::categories($request->user())->lockForUpdate()->findOrFail($productCategory->id);
            Gate::authorize('delete', $category);
            if (DB::table('product_categories')->where('parent_id', $category->id)->lockForUpdate()->first(['id'])
                || DB::table('products')->where('product_category_id', $category->id)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['category' => 'Kategori masih memiliki anak atau digunakan produk. Nonaktifkan kategori bila tidak lagi digunakan.']);
            }
            $before = $category->toArray();
            $category->delete();
            $this->audit($request, $category, 'delete', $before, []);
        }, 3);

        return to_route('product-categories.index')->with('success', 'Kategori produk berhasil dihapus.');
    }

    private function form(Request $request, ProductCategory $category, string $page): View
    {
        return view('product-categories.'.$page, [
            'category' => $category,
            'brands' => CatalogAccess::brands($request->user(), 'product_categories')->withTrashed()
                ->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $category->brand_id ?? 0))->orderBy('name')->get(),
            'parents' => CatalogAccess::categories($request->user())->when($category->exists, fn ($query) => $query->where('brand_id', $category->brand_id)->where('id', '!=', $category->id))
                ->with(['brand' => fn ($query) => $query->withTrashed()])->orderBy('name')->get(),
        ]);
    }

    private function validateHierarchy(Request $request, ProductCategory $category, array $data): void
    {
        CatalogAccess::brands($request->user(), 'product_categories')->withTrashed()
            ->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $category->brand_id ?? 0))
            ->lockForUpdate()->findOrFail($data['brand_id']);
        if (ProductCategory::where('brand_id', $data['brand_id'])->whereRaw('LOWER(slug) = ?', [strtolower($data['slug'])])
            ->when($category->exists, fn ($query) => $query->where('id', '!=', $category->id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug sudah digunakan dalam brand ini.']);
        }
        $visited = $category->exists ? [$category->id] : [];
        $parentId = $data['parent_id'] ?? null;
        while ($parentId) {
            if (in_array((int) $parentId, $visited, true)) {
                throw ValidationException::withMessages(['parent_id' => 'Kategori induk tidak boleh membentuk siklus.']);
            }
            $visited[] = (int) $parentId;
            $parent = CatalogAccess::categories($request->user())->where('brand_id', $data['brand_id'])->find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Kategori induk harus tersedia pada brand yang sama.']);
            }
            $parentId = $parent->parent_id;
        }
    }

    private function lockOrganization(Request $request): void
    {
        Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
        CatalogAccess::forget($request->user());
    }

    private function audit(Request $request, ProductCategory $category, string $event, array $before, ?array $after = null): void
    {
        AuditLog::create(['organization_id' => $request->user()->organization_id, 'user_id' => $request->user()->id,
            'event' => 'product_categories.'.$event, 'auditable_type' => $category->getMorphClass(), 'auditable_id' => $category->id,
            'old_values' => $before, 'new_values' => $after ?? $category->toArray(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
