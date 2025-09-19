<?php

use App\Http\Controllers\Api\ProductBulkPriceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductDeliveryOptionController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\SellerRegistrationController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->group(function () {
    Route::post('register/step1', [SellerRegistrationController::class, 'registerStep1']);
    Route::post('register/{store}/step2', [SellerRegistrationController::class, 'registerStep2']);
    Route::post('register/{store}/step3', [SellerRegistrationController::class, 'registerStep3']);


    //product routes

});

Route::prefix('seller')->middleware('auth:sanctum')->group(function () {

    Route::get('products', [ProductController::class, 'getAll']);
    Route::post('products/create', [ProductController::class, 'create']);
    Route::post('products/update/{id}', [ProductController::class, 'update']);
    Route::delete('products/delete/{id}', [ProductController::class, 'delete']);

    // Variants
    Route::post('products/{productId}/variants/create', [ProductVariantController::class, 'create']);
    Route::post('products/{productId}/variants/update/{variantId}', [ProductVariantController::class, 'update']);
    Route::delete('products/{productId}/variants/delete/{variantId}', [ProductVariantController::class, 'delete']);

    Route::post('products/{id}/bulk-prices', [ProductBulkPriceController::class, 'store']);
    Route::put('products/{id}/bulk-prices/{priceId}', [ProductBulkPriceController::class, 'update']);
    Route::delete('products/{id}/bulk-prices/{priceId}', [ProductBulkPriceController::class, 'delete']);


    // Product delivery options
    Route::post('products/{id}/delivery-options', [ProductDeliveryOptionController::class, 'attach']);
    Route::delete('products/{id}/delivery-options/{optionId}', [ProductDeliveryOptionController::class, 'detach']);

    Route::get('service/', [ServiceController::class, 'getAll']);
    Route::post('service/create', [ServiceController::class, 'create']);
    Route::post('service/update/{id}', [ServiceController::class, 'update']);
    Route::delete('service/delete/{id}', [ServiceController::class, 'delete']);
});
