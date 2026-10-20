<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\OperationalUnitController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\UnitOfMeasureController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('finance', [\App\Http\Controllers\AccountingController::class, 'index'])->defaults('module', 'finance')->name('finance.index');
    foreach (\App\Support\AccountingDefinition::MODELS as $module => $model) {
        foreach (['index'=>['GET',''], 'create'=>['GET','/create'], 'store'=>['POST',''], 'show'=>['GET','/{accounting_record}'], 'edit'=>['GET','/{accounting_record}/edit'], 'update'=>['PUT','/{accounting_record}'], 'destroy'=>['DELETE','/{accounting_record}']] as $method => [$verb,$suffix]) {
            if (\App\Support\AccountingDefinition::readOnly($module) && !in_array($method, ['index','show'])) continue;
            $route = Route::match([$verb], $module.$suffix, [\App\Http\Controllers\AccountingController::class, $method])->defaults('module', $module)->name($module.'.'.$method);
            if ($method === 'destroy') $route->defaults('operation', 'delete');
        }
        foreach (\App\Support\AccountingDefinition::actions($module) as $action => $caption) {
            Route::patch($module.'/{accounting_record}/'.$action, [\App\Http\Controllers\AccountingController::class, 'action'])->defaults('module', $module)->defaults('operation', $action)->name($module.'.'.$action);
        }
    }
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->middleware('auth')->name('dashboard');
Route::middleware('auth')->prefix('dashboards')->name('dashboards.')->group(function () {
    foreach (['operations', 'hr', 'growth'] as $domain) {
        Route::get($domain, [\App\Http\Controllers\DashboardController::class, 'index'])->defaults('domain', $domain)->name($domain);
    }
});

Route::middleware('auth')->prefix('master')->group(function () {
    Route::patch('products/{product}/restore', [\App\Http\Controllers\ProductController::class, 'restore'])->withTrashed()->name('products.restore');
    Route::patch('products/{product}/variants/{variant}/restore', [\App\Http\Controllers\ProductVariantController::class, 'restore'])->withTrashed()->name('products.variants.restore');
    Route::resource('products.variants', \App\Http\Controllers\ProductVariantController::class)->withTrashed(['index', 'show']);
    Route::resource('products', \App\Http\Controllers\ProductController::class)->withTrashed(['show']);
    Route::patch('recipes/{recipe}/restore', [\App\Http\Controllers\RecipeController::class, 'restore'])->withTrashed()->name('recipes.restore');
    Route::patch('recipes/{recipe}/versions/{version}/approve', [\App\Http\Controllers\RecipeVersionController::class, 'approve'])->name('recipes.versions.approve');
    Route::resource('recipes.versions', \App\Http\Controllers\RecipeVersionController::class)->withTrashed(['index', 'show']);
    Route::resource('recipes', \App\Http\Controllers\RecipeController::class)->withTrashed(['show']);

    Route::resource('product-categories', ProductCategoryController::class);
    Route::patch('inventory-items/{inventory_item}/restore', [InventoryItemController::class, 'restore'])->withTrashed()->name('inventory-items.restore');
    Route::resource('inventory-items', InventoryItemController::class)->withTrashed(['show']);
    Route::resource('uoms', UnitOfMeasureController::class);
    Route::resource('operational-units', OperationalUnitController::class);
    Route::patch('brands/{brand}/restore', [BrandController::class, 'restore'])->withTrashed()->name('brands.restore');
    Route::patch('brands/{brand}/activate', [BrandController::class, 'activate'])->name('brands.activate');
    Route::patch('brands/{brand}/deactivate', [BrandController::class, 'deactivate'])->name('brands.deactivate');
    Route::resource('brands', BrandController::class)->withTrashed(['show']);
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:60,1')->name('login.store');
});
Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('master')->group(function () {
    Route::patch('suppliers/{supplier}/restore', [\App\Http\Controllers\SupplierController::class, 'restore'])->withTrashed()->name('suppliers.restore');
    Route::resource('suppliers', \App\Http\Controllers\SupplierController::class)->withTrashed(['show']);
    Route::resource('positions', \App\Http\Controllers\PositionController::class);
    Route::patch('employees/{employee}/restore', [\App\Http\Controllers\EmployeeController::class, 'restore'])->withTrashed()->name('employees.restore');
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class)->withTrashed(['show']);
});

Route::middleware('auth')->prefix('purchasing')->group(function () {
    Route::get('purchase-requirements', [\App\Http\Controllers\PurchasingController::class, 'index'])->defaults('module', 'purchase-requirements')->name('purchase-requirements.index');
    foreach (array_keys(\App\Support\PurchasingDefinition::MODELS) as $module) {
        foreach (['index' => ['GET', ''], 'create' => ['GET', '/create'], 'store' => ['POST', ''], 'show' => ['GET', '/{purchasing_record}'],
            'edit' => ['GET', '/{purchasing_record}/edit'], 'update' => ['PUT', '/{purchasing_record}'], 'destroy' => ['DELETE', '/{purchasing_record}']] as $method => [$verb, $path]) {
            $route = Route::match([$verb], $module.$path, [\App\Http\Controllers\PurchasingController::class, $method])->defaults('module', $module)->name($module.'.'.$method);
            if ($module === 'purchase-orders' && $method === 'show') { $route->withTrashed(); }
        }
        $actions = match ($module) {
            'purchase-requisitions','purchase-orders' => ['submit','approve','reject','cancel'],
            'goods-receipts' => ['confirm','cancel'], 'supplier-contracts','supplier-items' => ['activate','deactivate'],
            'supplier-price-history' => ['close'],
        };
        foreach ($actions as $action) {
            Route::patch($module.'/{purchasing_record}/'.$action, [\App\Http\Controllers\PurchasingController::class, 'action'])
                ->defaults('module', $module)->defaults('action', $action)->name($module.'.'.$action);
        }
    }
});

Route::middleware('auth')->group(function () {
    Route::get('profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [\App\Http\Controllers\ProfileController::class, 'update'])->middleware('throttle:6,1')->name('profile.update');
    Route::put('profile/password', [\App\Http\Controllers\ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');
});

Route::middleware('auth')->group(function () {
    Route::get('pos', [\App\Http\Controllers\TradeController::class, 'create'])->defaults('module', 'sales-orders')->name('pos.index');
    foreach (\App\Support\TradeDefinition::MODELS as $module => $model) {
        foreach (['index'=>['GET',''],'create'=>['GET','/create'],'store'=>['POST',''],'show'=>['GET','/{trade_record}'],'edit'=>['GET','/{trade_record}/edit'],'update'=>['PUT','/{trade_record}'],'destroy'=>['DELETE','/{trade_record}']] as $method=>[$verb,$suffix]) {
            if (\App\Support\TradeDefinition::readOnly($module) && !in_array($method,['index','show'])) continue;
            Route::match([$verb],$module.$suffix,[\App\Http\Controllers\TradeController::class,$method])->defaults('module',$module)->name($module.'.'.$method);
        }
        foreach (\App\Support\TradeDefinition::actions($module) as $action=>$label) {
            Route::patch($module.'/{trade_record}/'.$action,[\App\Http\Controllers\TradeController::class,'action'])->defaults('module',$module)->defaults('operation',$action)->name($module.'.'.$action);
        }
    }
});

Route::middleware('auth')->group(function () {
    foreach (\App\Support\OperationsDefinition::models() as $module => $model) {
        foreach (['index'=>['GET',''],'create'=>['GET','/create'],'store'=>['POST',''],'show'=>['GET','/{operation_record}'],'edit'=>['GET','/{operation_record}/edit'],'update'=>['PUT','/{operation_record}'],'destroy'=>['DELETE','/{operation_record}']] as $method=>[$verb,$suffix]) {
            if ($module === 'production-material-requirements' && !in_array($method,['index','show'])) continue;
            Route::match([$verb],$module.$suffix,[\App\Http\Controllers\OperationsController::class,$method])->defaults('module',$module)->name($module.'.'.$method);
        }
        foreach (\App\Support\OperationsDefinition::actions($module) as $action=>$label) {
            Route::patch($module.'/{operation_record}/'.$action,[\App\Http\Controllers\OperationsController::class,'action'])->defaults('module',$module)->defaults('operation',$action)->name($module.'.'.$action);
        }
    }
});
