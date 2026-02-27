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

/*
|--------------------------------------------------------------------------
| Vendor Web (Session-based) Routes
|--------------------------------------------------------------------------
| These are "web" routes => CSRF protected.
| Your frontend must call GET /sanctum/csrf-cookie before POST login.
*/

Route::prefix('vendor')->group(function () {

    /*
    |-------------------------
    | Auth endpoints (web + csrf)
    |-------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/login',  [VendorAuthController::class, 'login']);
        Route::get('/me',      [VendorAuthController::class, 'me'])->middleware('auth:vendor');
        Route::post('/logout', [VendorAuthController::class, 'logout'])->middleware('auth:vendor');
    });

    /*
    |-------------------------
    | Vendor API endpoints (web session guard)
    |-------------------------
    */
    Route::prefix('api')->middleware(['auth:vendor'])->group(function () {

        // catalog
        Route::get('/catalog/departments', [VendorCatalogController::class, 'departments']);
        Route::get('/catalog/sub-departments/{departmentId}', [VendorCatalogController::class, 'subDepartments']);
        Route::get('/catalog/sub-sub-departments/{subDepartmentId}', [VendorCatalogController::class, 'subSubDepartments']);
        Route::get('/catalog/types', [VendorCatalogController::class, 'types']);
        Route::get('/catalog/brands', [VendorCatalogController::class, 'brands']);
        Route::get('/catalog/manufactures', [VendorCatalogController::class, 'manufactures']);

        // orders
        Route::get('/orders', [VendorOrdersController::class, 'index']);
        Route::get('/orders/{id}', [VendorOrdersController::class, 'show']);

        // earnings + payouts
        Route::get('/earnings/summary', [VendorEarningsController::class, 'summary']);
        Route::get('/payouts', [VendorPayoutsController::class, 'index']);

        // specifications
        Route::get(
            '/specifications/sub-sub-department/{id}',
            [VendorProductSpecificationController::class, 'bySubSubDepartment']
        );

        // temp products
        Route::get('/products-temp/next-id', [VendorTempProductController::class, 'nextId']);
        Route::post('/products-temp', [VendorTempProductController::class, 'store']);

        // products
        Route::get('/products/pending',  [VendorProductsController::class, 'pending']);
        Route::get('/products/approved', [VendorProductsController::class, 'approved']);
        Route::get('/products/summary',  [VendorProductsController::class, 'summary']);

        // Step 5
        Route::get('/products/pending/{id}',  [VendorProductsController::class, 'showPending']);
        Route::get('/products/approved/{id}', [VendorProductsController::class, 'showApproved']);
        Route::post('/products/approved/{id}/request-update', [VendorProductsController::class, 'requestUpdate']);
    });

    /*
    |-------------------------
    | Vendor SPA (served under /vendor)
    | IMPORTANT: do NOT match /vendor/api/* or /vendor/auth/*
    |-------------------------
    */
    Route::view('/', 'vendor');

    Route::view('/{any}', 'vendor')
        ->where('any', '^(?!api(?:/|$))(?!auth(?:/|$)).*$');
});

/*
|--------------------------------------------------------------------------
| Root SPA fallback (if your vendor UI is served on / as well)
|--------------------------------------------------------------------------
| CRITICAL: exclude /sanctum/* so /sanctum/csrf-cookie is never swallowed
| (this is the #1 reason for CSRF token mismatch in production with SPAs).
*/
Route::view('/', 'vendor');

Route::view('/{any}', 'vendor')
    ->where('any', '^(?!vendor(?:/|$))(?!sanctum(?:/|$))(?!api(?:/|$)).*$');