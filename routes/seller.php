<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BoostProductController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductBulkPriceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductDeliveryOptionController;
use App\Http\Controllers\Api\ProductStatController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\SavedCardController;
use App\Http\Controllers\Api\SellerChatController;
use App\Http\Controllers\Api\SellerOnboardingController;
use App\Http\Controllers\Api\SellerOrderController;
use App\Http\Controllers\Api\SellerRegistrationController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceStatController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\LoyaltyController as SellerLoyalty;
Route::prefix('seller')->group(function () {
    Route::post('register/step1', [SellerRegistrationController::class, 'registerStep1']);
    Route::post('register/{storeId}/step2', [SellerRegistrationController::class, 'registerStep2']);
    Route::post('register/{storeId}/step3', [SellerRegistrationController::class, 'registerStep3']);


    //product routes

});
Route::post('auth/seller/start', [SellerOnboardingController::class, 'start']);

/* Authenticated micro-steps */
Route::middleware('auth:sanctum')->prefix('seller/onboarding')->group(function () {
    // ---- Level 1
    Route::post('level1/profile-media',     [SellerOnboardingController::class, 'level1ProfileMedia']);   // 1.2
    Route::post('level1/categories-social', [SellerOnboardingController::class, 'level1Categories']);     // 1.3

    // ---- Level 2
    Route::post('level2/business-details',  [SellerOnboardingController::class, 'level2Business']);       // 2.1
    Route::post('level2/documents',         [SellerOnboardingController::class, 'level2Documents']);      // 2.2

    // ---- Level 3
    Route::post('level3/physical-store',    [SellerOnboardingController::class, 'level3Physical']);       // 3.1
    Route::post('level3/utility-bill',      [SellerOnboardingController::class, 'level3Utility']);        // 3.2

    Route::post('level3/address',          [SellerOnboardingController::class, 'addAddress']);           // 3.3 create
    Route::delete('level3/address/{id}',   [SellerOnboardingController::class, 'deleteAddress']);        // 3.3 delete

    Route::post('level3/delivery',         [SellerOnboardingController::class, 'addDelivery']);          // 3.4 create
    Route::delete('level3/delivery/{id}',  [SellerOnboardingController::class, 'deleteDelivery']);       // 3.4 delete

    Route::post('level3/theme',             [SellerOnboardingController::class, 'level3Theme']);          // 3.5

    // ---- Progress / Submit
    Route::get('progress',                 [SellerOnboardingController::class, 'progress']);
    Route::post('submit',                  [SellerOnboardingController::class, 'submitForReview']);


    Route::get('store/overview',  [SellerOnboardingController::class, 'overview']);   // store + relations + progress
    Route::get('onboarding/progress', [SellerOnboardingController::class, 'progress']); // already exists

    // Per-level getters (for edit screens)
    Route::get('onboarding/level/1', [SellerOnboardingController::class, 'level1Data']);
    Route::get('onboarding/level/2', [SellerOnboardingController::class, 'level2Data']);
    Route::get('onboarding/level/3', [SellerOnboardingController::class, 'level3Data']);

    // Standalone lists (used by pickers/modals)
    Route::get('store/addresses',      [SellerOnboardingController::class, 'listAddresses']);
    Route::get('store/delivery',       [SellerOnboardingController::class, 'listDelivery']);
    Route::get('store/social-links',   [SellerOnboardingController::class, 'listSocialLinks']);
    Route::get('store/categories',     [SellerOnboardingController::class, 'listSelectedCategories']); // selected only
    Route::get('catalog/categories',   [SellerOnboardingController::class, 'listAllCategories']);      // all available
});

Route::prefix('seller')->middleware('auth:sanctum')->group(function () {

    Route::get('products', [ProductController::class, 'getAll']);
    Route::post('products/create', [ProductController::class, 'create']);
    Route::post('products/update/{id}', [ProductController::class, 'update']);
    Route::delete('products/delete/{id}', [ProductController::class, 'delete']);

    //Store Orders
    // Variants
    Route::post('products/{productId}/variants/create', [ProductVariantController::class, 'create']);
    Route::post('products/{productId}/variants/update/{variantId}', [ProductVariantController::class, 'update']);
    Route::delete('products/{productId}/variants/delete/{variantId}', [ProductVariantController::class, 'delete']);
    Route::post('products/{id}/bulk-prices', [ProductBulkPriceController::class, 'store']);
    Route::post('products/{id}/bulk-prices/{priceId}', [ProductBulkPriceController::class, 'update']);
    Route::delete('products/{id}/bulk-prices/{priceId}', [ProductBulkPriceController::class, 'delete']);
    // Product delivery options
    Route::post('products/{id}/delivery-options', [ProductDeliveryOptionController::class, 'attach']);
    Route::delete('products/{id}/delivery-options/{optionId}', [ProductDeliveryOptionController::class, 'detach']);
    Route::get('service/', [ServiceController::class, 'getAll']);
    Route::post('service/create', [ServiceController::class, 'create']);
    Route::post('service/update/{id}', [ServiceController::class, 'update']);
    Route::delete('service/delete/{id}', [ServiceController::class, 'delete']);
    Route::get('service/{id}', [ServiceController::class, 'getById']);

    //Permote a product

     Route::get('/boosts',                   [BoostProductController::class, 'index']);
    Route::post('/boosts/preview',          [BoostProductController::class, 'preview']);
    Route::post('/boosts',                  [BoostProductController::class, 'store']);
    Route::get('/boosts/{boost}',           [BoostProductController::class, 'show']);
    Route::patch('/boosts/{boost}/status',  [BoostProductController::class, 'updateStatus']);
    Route::patch('/boosts/{boost}/metrics', [BoostProductController::class, 'updateMetrics']);


      Route::get('/plans', [SubscriptionController::class, 'plans']);                 // list available plans
    Route::get('/subscriptions', [SubscriptionController::class, 'mySubscriptions']); // seller's subscriptions
    Route::post('/subscriptions', [SubscriptionController::class, 'subscribe']);      // subscribe to a plan
    Route::patch('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);


      Route::get('/coupons', [CouponController::class,'index']);
    Route::post('/coupons', [CouponController::class,'store']);
    Route::put('/coupons/{coupon}', [CouponController::class,'update']);
    Route::delete('/coupons/{coupon}', [CouponController::class,'destroy']);

    Route::post('/coupons/apply/{code}', [CouponController::class,'apply']);


        Route::get('/announcements', [AnnouncementController::class,'index']);
    Route::post('/announcements', [AnnouncementController::class,'store']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class,'destroy']);

    // Banners
    Route::get('/banners', [BannerController::class,'index']);
    Route::post('/banners', [BannerController::class,'store']);
    Route::delete('/banners/{banner}', [BannerController::class,'destroy']);

     Route::get('chat/', [SellerChatController::class, 'list']);
    Route::get('chat/{chatId}/messages', [SellerChatController::class, 'messages']);
    Route::post('chat/{chatId}/send', [SellerChatController::class, 'send']);

      Route::get('/products/{id}/stats', [ProductStatController::class,'getStats']);  // chart data (daily)
    Route::get('/products/{id}/stats/totals', [ProductStatController::class,'totals']); // overall totals
    Route::get('/services/{id}/stats', [ServiceStatController::class,'getStats']);      // daily chart data
    Route::get('/services/{id}/stats/totals', [ServiceStatController::class,'totals']); // totals

      Route::get('/cards', [SavedCardController::class,'index']);
    Route::post('/cards', [SavedCardController::class,'store']);
    Route::patch('/cards/{id}/active', [SavedCardController::class,'setActive']);
    Route::patch('/cards/{id}/autodebit', [SavedCardController::class,'toggleAutodebit']);
    Route::delete('/cards/{id}', [SavedCardController::class,'destroy']);

        Route::put('/announcements/{announcement}', [AnnouncementController::class,'update']);   // ✅ update
            Route::post('/banners/{banner}', [BannerController::class,'update']);   // ✅ update

    Route::get('orders', [SellerOrderController::class, 'index']);
    Route::get('orders/{id}', [SellerOrderController::class, 'getOrder']);
    Route::post('orders/{orderId}/out-for-deliver', [SellerOrderController::class, 'markOutForDelivery']); //id will be used here will be the uid of sotore order means id from the list not the order_id
    Route::post('orders/{orderId}/delivered', [SellerOrderController::class, 'verifyDeliveryCode']);
});

Route::middleware('auth:sanctum')->prefix('seller/loyalty')->group(function () {
    Route::get('settings', [SellerLoyalty::class, 'settings']);
    Route::post('settings', [SellerLoyalty::class, 'settings']); // update
    Route::get('customers', [SellerLoyalty::class, 'customers']);
});