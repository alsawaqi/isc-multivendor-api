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

// auth endpoints (session + csrf protected because these are web routes)
Route::prefix('vendor')->group(function () {


    Route::post('/auth/login', [VendorAuthController::class, 'login']);
    Route::get('/auth/me', [VendorAuthController::class, 'me']);
    Route::post('/auth/logout', [VendorAuthController::class, 'logout']);


    Route::prefix('api')->middleware(['auth:vendor'])->group(function () {
            // catalog
            Route::get('/catalog/departments', [VendorCatalogController::class, 'departments']);
            Route::get('/catalog/sub-departments/{departmentId}', [VendorCatalogController::class, 'subDepartments']);
            Route::get('/catalog/sub-sub-departments/{subDepartmentId}', [VendorCatalogController::class, 'subSubDepartments']);
            Route::get('/catalog/types', [VendorCatalogController::class, 'types']);
            Route::get('/catalog/brands', [VendorCatalogController::class, 'brands']);
            Route::get('/catalog/manufactures', [VendorCatalogController::class, 'manufactures']);

            // vendor orders (Step 1)
            Route::get('/orders', [VendorOrdersController::class, 'index']);
            Route::get('/orders/{id}', [VendorOrdersController::class, 'show']);
  
            Route::get('/earnings/summary', [VendorEarningsController::class, 'summary']);

                    Route::get('/payouts', [VendorPayoutsController::class, 'index']);


                    Route::get(
                        '/specifications/sub-sub-department/{id}',
                        [VendorProductSpecificationController::class, 'bySubSubDepartment']
                    );

                    // temp products
                    Route::get('/products-temp/next-id', [VendorTempProductController::class, 'nextId']);
                    Route::post('/products-temp', [VendorTempProductController::class, 'store']);




                    Route::get('products/pending',  [VendorProductsController::class, 'pending']);
                    Route::get('products/approved', [VendorProductsController::class, 'approved']);
                    Route::get('products/summary',  [VendorProductsController::class, 'summary']);


                    // ✅ Step 5
            Route::get('products/pending/{id}',  [VendorProductsController::class, 'showPending']);
            Route::get('products/approved/{id}', [VendorProductsController::class, 'showApproved']);
            Route::post('products/approved/{id}/request-update', [VendorProductsController::class, 'requestUpdate']);
    });

    Route::view('/{any}', 'vendor')->where('any', '.*');
});



 


// Example: if vendor SPA lives under /vendor
Route::get('/{any?}', function () {
    return view('vendor'); // your Blade that contains <div id="app"></div> and @vite
})->where('any', '.*');
