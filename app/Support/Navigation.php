<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\InventoryItem;
use App\Models\OperationalUnit;
use App\Models\ProductCategory;
use App\Models\RoleAssignment;
use App\Models\UnitOfMeasure;
use App\Policies\OperationalUnitAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Navigation
{
    public function data(): array
    {
        if (request()->attributes->has('admin_navigation')) {
            return request()->attributes->get('admin_navigation');
        }
        $user = request()->user();
        $organization = $user?->organization;
        $assignments = collect();
        if ($user && $user->status === 'ACTIVE' && ! $user->trashed()) {
            $assignments = RoleAssignment::query()->where('user_id', $user->id)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('operational_unit_id')->orWhereHas('operationalUnit', fn ($unit) => $unit->where('organization_id', $organization?->id)))
                ->whereHas('role', fn ($query) => $query
                    ->where(fn ($query) => $query->where('organization_id', $organization?->id)->whereNotNull('organization_id'))
                    ->orWhere(fn ($query) => $query->whereNull('organization_id')->where('is_system', true)))
                ->with('role.permissions')->get();
        }
        $roles = $assignments->pluck('role.code')->unique()->all();
        $permissions = $assignments->flatMap(fn ($assignment) => $assignment->role->permissions->pluck('code'))->unique()->all();
        $super = in_array('SUPER_ADMIN', $roles, true);
        $gates = [
            'dashboards.operations' => $user && Gate::forUser($user)->allows('view-dashboard', 'operations'),
            'dashboards.hr' => $user && Gate::forUser($user)->allows('view-dashboard', 'hr'),
            'dashboards.growth' => $user && Gate::forUser($user)->allows('view-dashboard', 'growth'),
            'suppliers' => $user && Gate::forUser($user)->allows('viewAny', \App\Models\Supplier::class),
            'positions' => $user && Gate::forUser($user)->allows('viewAny', \App\Models\Position::class),
            'employees' => $user && Gate::forUser($user)->allows('viewAny', \App\Models\Employee::class),
            'products' => $user && Gate::forUser($user)->allows('viewAny', \App\Models\Product::class),
            'recipes' => $user && Gate::forUser($user)->allows('viewAny', \App\Models\Recipe::class),
            'product-categories' => $user && Gate::forUser($user)->allows('viewAny', ProductCategory::class),
            'inventory-items' => $user && Gate::forUser($user)->allows('viewAny', InventoryItem::class),
            'uoms' => $user && Gate::forUser($user)->allows('viewAny', UnitOfMeasure::class),
            'brands' => $user && Gate::forUser($user)->allows('viewAny', Brand::class),
            'operational-units' => $user && Gate::forUser($user)->allows('viewAny', OperationalUnit::class),
        ];
        foreach (array_keys(PurchasingDefinition::LABELS) as $module) {
            $gates[$module] = $user && Gate::forUser($user)->allows('purchasing-view', $module);
        }
        foreach (array_keys(TradeDefinition::LABELS) as $module) {
            $gates[$module] = $user && Gate::forUser($user)->allows('trade-view', $module);
        }
        $gates['pos'] = $user && !TradeAccess::grants($user, 'sales-orders')->isEmpty() && !TradeAccess::grants($user, 'sales-orders', 'create')->isEmpty();
        foreach (array_keys(OperationsDefinition::labels()) as $module) $gates[$module] = $user && Gate::forUser($user)->allows('operations-view', $module);
        foreach (array_keys(AccountingDefinition::LABELS) as $module) $gates[$module] = $user && Gate::forUser($user)->allows('accounting-view', $module);
        $badges = request()->attributes->get('navigation_badges', []);
        $groups = [];
        foreach (config('navigation.groups', []) as $group) {
            $group['children'] = $this->items($group['children'], $roles, $permissions, $super, $gates, $badges);
            if ($group['children'] === []) {
                continue;
            }
            $group['active'] = collect($group['children'])->contains('active', true);
            $group['count'] = $this->badge($badges, $group['badge']);
            $groups[] = $group;
        }
        $units = $user && $gates['operational-units']
            ? OperationalUnitAccess::query($user)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'type'])
            : collect();
        $selectedId = request()->query('unit');
        $activeUnit = is_scalar($selectedId) ? $units->firstWhere('id', (string) $selectedId) : null;
        $routeUnit = request()->route('operational_unit');
        if ($routeUnit instanceof OperationalUnit) {
            $activeUnit = $units->firstWhere('id', $routeUnit->id);
        }
        $dashboardPage = request()->routeIs('dashboard', 'dashboards.*');
        if ($dashboardPage && $user) {
            $domain = request()->route()->defaults['domain'] ?? 'overview';
            $units = request()->attributes->get('dashboard_units_'.$domain)
                ?? DashboardAccess::units($user, $domain)->orderBy('name')->get(['id', 'name', 'type']);
            $activeUnit = is_scalar($selectedId) ? $units->firstWhere('id', (string) $selectedId) : null;
        } else {
            $activeUnit ??= $units->first();
        }
        $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')]];
        $routeName = request()->route()?->getName() ?? '';
        foreach (DashboardAccess::LABELS as $domain => $label) {
            if ($routeName === 'dashboards.'.$domain) { $breadcrumbs[] = ['label' => $label, 'url' => null]; }
        }
        foreach (['products' => 'Produk & Varian', 'recipes' => 'Recipe & BOM', 'product-categories' => 'Kategori Produk', 'inventory-items' => 'Bahan & Inventory Item', 'uoms' => 'Satuan/UOM', 'brands' => 'Brand', 'operational-units' => 'Operational Unit'] as $prefix => $label) {
            if (Str::startsWith($routeName, $prefix.'.')) {
                $breadcrumbs[] = ['label' => $label, 'url' => route($prefix.'.index')];
                $action = Str::afterLast($routeName, '.');
                if ($action !== 'index') {
                    $breadcrumbs[] = ['label' => ['create' => 'Tambah', 'edit' => 'Edit', 'show' => 'Detail'][$action] ?? Str::headline($action), 'url' => null];
                }
            }
        }
        foreach (PurchasingDefinition::LABELS as $module => $label) {
            if (str_starts_with($routeName, $module.'.')) { $breadcrumbs[] = ['label' => $label, 'url' => route($module.'.index')]; }
        }
        foreach (TradeDefinition::LABELS as $module => $label) {
            if (str_starts_with($routeName, $module.'.') || ($module === 'sales-orders' && $routeName === 'pos.index')) $breadcrumbs[] = ['label' => $label, 'url' => route($module.'.index')];
        }
        foreach (OperationsDefinition::labels() as $module => $label) {
            if (str_starts_with($routeName, $module.'.')) $breadcrumbs[] = ['label' => $label, 'url' => route($module.'.index')];
        }
        foreach (AccountingDefinition::LABELS as $module => $label) {
            if (str_starts_with($routeName, $module.'.')) $breadcrumbs[] = ['label' => $label, 'url' => route($module.'.index')];
        }
        if (str_starts_with($routeName, 'profile.')) {
            $breadcrumbs[] = ['label' => 'Profil Saya', 'url' => route('profile.show')];
            if ($routeName === 'profile.edit') $breadcrumbs[] = ['label' => 'Pengaturan Akun', 'url' => null];
        }
        $data = [
            'groups' => $groups, 'userName' => $user?->name ?? 'Pengguna',
            'organizationName' => $organization?->name ?? 'Organization belum tersedia',
            'initials' => Str::upper(collect(explode(' ', trim($user?->name ?? 'GFO')))->filter()->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode('')),
            'units' => $units, 'activeUnit' => $activeUnit, 'breadcrumbs' => $breadcrumbs,
            'profileUrl' => Route::has('profile.show') ? route('profile.show') : null,
            'accountUrl' => Route::has('profile.edit') ? route('profile.edit') : null,
            'notificationUrl' => Route::has('notifications.index') ? route('notifications.index') : null,
        ];
        request()->attributes->set('admin_navigation', $data);

        return $data;
    }

    private function items(array $items, array $roles, array $permissions, bool $super, array $gates, array $badges): array
    {
        $result = [];
        foreach (collect($items)->sortBy('order') as $item) {
            $visible = $super || in_array('*', $item['roles'], true) || array_intersect($roles, $item['roles'])
                || ($item['permission'] && in_array($item['permission'], $permissions, true));
            if (! $visible) {
                continue;
            }
            $item['children'] = $this->items($item['children'], $roles, $permissions, $super, $gates, $badges);
            $exists = Route::has($item['route']);
            $authorized = ! isset($item['gate']) || ($gates[$item['gate']] ?? false);
            $item['enabled'] = $item['enabled'] && $exists && $authorized;
            $item['url'] = $item['enabled'] ? route($item['route'], $item['parameters']) : null;
            $item['disabledLabel'] = $exists && ! $authorized ? 'Akses dibatasi' : 'Segera';
            $item['active'] = request()->routeIs(...$item['active']);
            if ($item['parameters'] !== []) {
                $routeUnit = request()->route('operational_unit');
                $type = $routeUnit instanceof OperationalUnit ? $routeUnit->type : request()->query('type');
                $item['active'] = $item['active'] && $type === ($item['parameters']['type'] ?? null);
            }
            $item['count'] = $this->badge($badges, $item['badge']);
            $result[] = $item;
        }

        return $result;
    }

    private function badge(array $badges, ?string $key): ?int
    {
        $value = $key ? ($badges[$key] ?? null) : null;

        return is_int($value) && $value >= 0 ? $value : null;
    }
}
