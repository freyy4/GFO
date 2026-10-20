<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\UnitOfMeasure;
use App\Policies\LoginPolicy;
use App\Policies\OperationalUnitAccess;
use App\Support\CatalogAccess;
use App\View\Composers\NavigationComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['view', 'create', 'action'] as $ability) {
            Gate::define('accounting-'.$ability, [\App\Policies\AccountingPolicy::class, $ability]);
        }
        Route::bind('accounting_record', fn (string $value) => \App\Support\AccountingAccess::query(auth()->user(), request()->route()->defaults['module'])->findOrFail($value));
        View::composer(['app', 'dashboard'], NavigationComposer::class);

        foreach (['view', 'create', 'action'] as $ability) {
            Gate::define('operations-'.$ability, function ($user, $module, ...$arguments) use ($ability) {
                $policy = \App\Support\OperationsDefinition::production($module) ? new \App\Policies\ProductionPolicy : new \App\Policies\WorkforcePolicy;
                return $policy->$ability($user, $module, ...$arguments);
            });
        }
        Route::bind('operation_record', fn (string $value) => \App\Support\OperationsAccess::query(auth()->user(), request()->route()->defaults['module'])->findOrFail($value));

        Gate::define('trade-view', [\App\Policies\TradePolicy::class, 'view']);
        Gate::define('trade-create', [\App\Policies\TradePolicy::class, 'create']);
        Gate::define('trade-action', [\App\Policies\TradePolicy::class, 'action']);
        Route::bind('trade_record', function (string $value) {
            return \App\Support\TradeAccess::query(auth()->user(), request()->route()->defaults['module'])->findOrFail($value);
        });
        Gate::define('manage-profile', [\App\Policies\ProfilePolicy::class, 'manage']);
        Gate::define('purchasing-view', [\App\Policies\PurchasingPolicy::class, 'view']);
        Gate::define('purchasing-create', [\App\Policies\PurchasingPolicy::class, 'create']);
        Gate::define('purchasing-action', [\App\Policies\PurchasingPolicy::class, 'action']);
        Route::bind('purchasing_record', function (string $value) {
            $module = request()->route()->defaults['module'];
            $query = \App\Support\PurchasingAccess::query(auth()->user(), $module);
            if ($module === 'purchase-orders' && request()->route()->allowsTrashedBindings()) { $query->withTrashed(); }
            return $query->findOrFail($value);
        });

        Gate::define('view-dashboard', [\App\Policies\DashboardPolicy::class, 'view']);
        Gate::define('login', [LoginPolicy::class, 'login']);
        Gate::define('logout', [LoginPolicy::class, 'logout']);

        foreach (['product' => 'products', 'recipe' => 'recipes', 'variant' => 'variants', 'version' => 'versions'] as $parameter => $scope) {
            Route::bind($parameter, function (string $value) use ($parameter, $scope) {
                $query = \App\Support\ProductRecipeAccess::$scope(auth()->user());
                if ($parameter !== 'version' && request()->route()->allowsTrashedBindings()) { $query->withTrashed(); }
                if ($parameter === 'variant') { $query->where('product_id', request()->route('product')->id); }
                if ($parameter === 'version') { $query->where('recipe_id', request()->route('recipe')->id); }
                return $query->findOrFail($value);
            });
        }

        Route::bind('product_category', fn (string $value) => CatalogAccess::categories(auth()->user())->findOrFail($value));
        Route::bind('inventory_item', function (string $value) {
            $query = CatalogAccess::items(auth()->user());
            if (request()->route()->allowsTrashedBindings()) {
                $query->withTrashed();
            }

            return $query->findOrFail($value);
        });

        Route::bind('uom', function (string $value) {
            Gate::authorize('viewAny', UnitOfMeasure::class);

            return UnitOfMeasure::findOrFail($value);
        });

        Route::bind('operational_unit', fn (string $value) => OperationalUnitAccess::query(auth()->user())->findOrFail($value));

        foreach (['supplier' => 'suppliers', 'position' => 'positions', 'employee' => 'employees'] as $parameter => $module) {
            Route::bind($parameter, function (string $value) use ($module) {
                $query = \App\Support\DirectoryAccess::query(auth()->user(), $module);
                if ($module !== 'positions' && request()->route()->allowsTrashedBindings()) { $query->withTrashed(); }
                return $query->findOrFail($value);
            });
        }

        Route::bind('brand', function (string $value) {
            $query = Brand::query()->where('organization_id', auth()->user()?->organization_id);
            if (request()->route()->allowsTrashedBindings()) {
                $query->withTrashed();
            }

            return $query->findOrFail($value);
        });
    }
}
