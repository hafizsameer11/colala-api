<?php

use App\Http\Controllers\Api\ProductController;
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

Route::prefix('seller/services')->middleware('auth:sanctum')->group(function () {

     Route::get('products', [ProductController::class, 'getAll']);
    Route::post('products/create', [ProductController::class, 'create']);
    Route::post('products/update/{id}', [ProductController::class, 'update']);
    Route::delete('products/delete/{id}', [ProductController::class, 'delete']);

    // Variants
    Route::post('products/{productId}/variants/create', [ProductVariantController::class, 'create']);
    Route::post('products/{productId}/variants/update/{variantId}', [ProductVariantController::class, 'update']);
    Route::delete('products/{productId}/variants/delete/{variantId}', [ProductVariantController::class, 'delete']);
    Route::get('/', [ServiceController::class, 'getAll']);
    Route::post('/create', [ServiceController::class, 'create']);
    Route::post('/update/{id}', [ServiceController::class, 'update']);
    Route::delete('/delete/{id}', [ServiceController::class, 'delete']);
});