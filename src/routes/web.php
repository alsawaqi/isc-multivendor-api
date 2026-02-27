<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\Vendor\VendorCatalogController;
use App\Http\Controllers\Vendor\VendorTempProductController;
use App\Http\Controllers\VendorProductSpecificationController;
use App\Http\Controllers\Vendor\VendorProductsController;
use App\Http\Controllers\Vendor\VendorOrdersController;
use App\Http\Controllers\Vendor\VendorPayoutsController;
use App\Http\Controllers\Vendor\VendorEarningsController;

Route::prefix('vendor')->group(function () {

    // Auth (web routes -> CSRF protected)
    Route::prefix('auth')->group(function () {
        Route::post('/login',  [VendorAuthController::class, 'login']);
        Route::get('/me',      [VendorAuthController::class, 'me'])->middleware('auth:vendor');
        Route::post('/logout', [VendorAuthController::class, 'logout'])->middleware('auth:vendor');
    });

    // Vendor API (session guard)
    Route::prefix('api')->middleware(['auth:vendor'])->group(function () {

        Route::get('/catalog/departments', [VendorCatalogController::class, 'departments']);
        Route::get('/catalog/sub-departments/{departmentId}', [VendorCatalogController::class, 'subDepartments']);
        Route::get('/catalog/sub-sub-departments/{subDepartmentId}', [VendorCatalogController::class, 'subSubDepartments']);
        Route::get('/catalog/types', [VendorCatalogController::class, 'types']);
        Route::get('/catalog/brands', [VendorCatalogController::class, 'brands']);
        Route::get('/catalog/manufactures', [VendorCatalogController::class, 'manufactures']);

        Route::get('/orders', [VendorOrdersController::class, 'index']);
        Route::get('/orders/{id}', [VendorOrdersController::class, 'show']);

        Route::get('/earnings/summary', [VendorEarningsController::class, 'summary']);
        Route::get('/payouts', [VendorPayoutsController::class, 'index']);

        Route::get('/specifications/sub-sub-department/{id}', [VendorProductSpecificationController::class, 'bySubSubDepartment']);

        Route::get('/products-temp/next-id', [VendorTempProductController::class, 'nextId']);
        Route::post('/products-temp', [VendorTempProductController::class, 'store']);

        Route::get('/products/pending',  [VendorProductsController::class, 'pending']);
        Route::get('/products/approved', [VendorProductsController::class, 'approved']);
        Route::get('/products/summary',  [VendorProductsController::class, 'summary']);

        Route::get('/products/pending/{id}',  [VendorProductsController::class, 'showPending']);
        Route::get('/products/approved/{id}', [VendorProductsController::class, 'showApproved']);
        Route::post('/products/approved/{id}/request-update', [VendorProductsController::class, 'requestUpdate']);
    });

  
});



  // Vendor SPA (ONLY under /vendor)
    Route::view('/', 'vendor');
    Route::view('/{any}', 'vendor')
        ->where('any', '^(?!api(?:/|$))(?!auth(?:/|$)).*$');

