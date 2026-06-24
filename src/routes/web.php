<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\Vendor\VendorCatalogController;
use App\Http\Controllers\Vendor\VendorTempProductController;
use App\Http\Controllers\VendorProductSpecificationController;
use App\Http\Controllers\Vendor\VendorProductsController;
use App\Http\Controllers\Vendor\VendorStockController;
use App\Http\Controllers\Vendor\VendorOrdersController;
use App\Http\Controllers\Vendor\VendorPayoutsController;
use App\Http\Controllers\Vendor\VendorEarningsController;
use App\Http\Controllers\Vendor\VendorProductEngagementController;
use App\Http\Controllers\Vendor\VendorProfileController;
use App\Http\Controllers\Vendor\VendorNotificationController;
use App\Http\Controllers\Vendor\GeoController;

Route::prefix('vendor')->group(function () {

    // Auth (web routes -> CSRF protected)
    Route::prefix('auth')->group(function () {
        Route::post('/login',           [VendorAuthController::class, 'login']);
        Route::post('/register',        [VendorAuthController::class, 'register']); // public self-registration
        Route::get('/me',               [VendorAuthController::class, 'me'])->middleware('auth:vendor');
        Route::post('/logout',          [VendorAuthController::class, 'logout'])->middleware('auth:vendor');
        Route::post('/change-password', [VendorAuthController::class, 'changePassword'])->middleware('auth:vendor');
    });

    // Vendor API (session guard)
    Route::prefix('api')->middleware(['auth:vendor', 'vendor.active'])->group(function () {


        Route::get('/profile', [VendorProfileController::class, 'show']);
        Route::put('/profile', [VendorProfileController::class, 'update']);
        Route::post('/profile/documents', [VendorProfileController::class, 'upsertDocuments']);
        Route::post('/profile/submit', [VendorProfileController::class, 'submitForReview']);

        // Vendor notification bell
        Route::get('/notifications', [VendorNotificationController::class, 'index']);
        Route::post('/notifications/mark-as-read', [VendorNotificationController::class, 'markAllRead']);

        // Geography lookups for the onboarding/profile form
        Route::get('/geo/countries', [GeoController::class, 'countries']);
        Route::get('/geo/regions/{country}', [GeoController::class, 'regions']);
        Route::get('/geo/districts/{region}', [GeoController::class, 'districts']);
        Route::get('/geo/cities/{district}', [GeoController::class, 'cities']);

        Route::middleware('vendor.approved')->group(function () {
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
            Route::get('/reviews', [VendorProductEngagementController::class, 'reviews']);
            Route::post('/reviews/{review}/reply', [VendorProductEngagementController::class, 'replyReview']);
            Route::get('/questions', [VendorProductEngagementController::class, 'questions']);
            Route::post('/questions/{question}/answer', [VendorProductEngagementController::class, 'answerQuestion']);

            Route::get('/specifications/sub-sub-department/{id}', [VendorProductSpecificationController::class, 'bySubSubDepartment']);

            Route::get('/products-temp/next-id', [VendorTempProductController::class, 'nextId']);
            Route::post('/products-temp', [VendorTempProductController::class, 'store']);
            Route::post('/products-temp/{id}/resubmit', [VendorTempProductController::class, 'update']);
            Route::match(['put', 'patch'], '/products-temp/{id}', [VendorTempProductController::class, 'update']);

            Route::get('/products/pending',  [VendorProductsController::class, 'pending']);
            Route::get('/products/approved', [VendorProductsController::class, 'approved']);
            Route::get('/products/summary',  [VendorProductsController::class, 'summary']);

            Route::get('/products/pending/{id}',  [VendorProductsController::class, 'showPending']);
            Route::get('/products/approved/{id}', [VendorProductsController::class, 'showApproved']);
            Route::post('/products/approved/{id}/request-update', [VendorProductsController::class, 'requestUpdate']);

            Route::get('/stock/products', [VendorStockController::class, 'index']);
            Route::post('/stock/products/{id}/adjust', [VendorStockController::class, 'adjust']);
            Route::get('/stock/products/{id}/movements', [VendorStockController::class, 'movements']);
        });
    });

  
});



  // Vendor SPA (ONLY under /vendor)
    Route::view('/', 'vendor');
    Route::view('/{any}', 'vendor')
        ->where('any', '^(?!api(?:/|$))(?!auth(?:/|$)).*$');
