<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\Buyer\CartController;
use App\Http\Controllers\Api\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\DisputeController;
use App\Http\Controllers\Api\Buyer\EscrowController;
use App\Http\Controllers\Buyer\LoyaltyController;
use App\Http\Controllers\Api\Buyer\OrderController;
use App\Http\Controllers\Api\Buyer\ProductBrowseController;
use App\Http\Controllers\Api\Buyer\ReviewController;
use App\Http\Controllers\Api\Buyer\UserAddressController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StoreReviewController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Buyer\ChatController;
use App\Http\Controllers\Buyer\SavedItemController;
use App\Http\Controllers\Buyer\StoreFollowController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Seller\LoyaltyController as SellerLoyalty;
// use App\Http\Controllers\Api\Buyer\LoyaltyController as BuyerLoyalty;

require __DIR__ . '/seller.php';

Route::get('/optimize-app', function () {
    Artisan::call('optimize:clear'); // Clears cache, config, route, and view caches
    Artisan::call('cache:clear');    // Clears application cache
    Artisan::call('config:clear');   // Clears configuration cache
    Artisan::call('route:clear');    // Clears route cache
    Artisan::call('view:clear');     // Clears compiled Blade views
    Artisan::call('config:cache');   // Rebuilds configuration cache
    Artisan::call('route:cache');    // Rebuilds route cache
    Artisan::call('view:cache');     // Precompiles Blade templates
    Artisan::call('optimize');       // Optimizes class loading

    return "Application optimized and caches cleared successfully!";
});
Route::get('/migrate', function () {
    Artisan::call('migrate');
    return response()->json(['message' => 'Migration successful'], 200);
});

Route::get('/un-auth', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/categories', [App\Http\Controllers\Api\CategoryController::class, 'getAll']);
    //admin routes
    Route::post('/create-category', [App\Http\Controllers\Api\CategoryController::class, 'create']);
    Route::post('/update-category/{id}', [App\Http\Controllers\Api\CategoryController::class, 'update']);

    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandController::class, 'getAll']);
        Route::post('/', [BrandController::class, 'create']);
        Route::put('{id}', [BrandController::class, 'update']);
        Route::delete('{id}', [BrandController::class, 'delete']);

        //product
    });
    Route::get('buyer/product/get-all', [ProductController::class, 'getAllforBuyer']);
    // routes/api.php
    Route::get('/search', [SearchController::class, 'search']);

    Route::get('/posts', [PostController::class, 'index']);             // list posts
    Route::post('/posts', [PostController::class, 'store']);            // create post
    Route::get('/posts/{id}', [PostController::class, 'show']);         // show single post
    Route::post('/posts/{id}', [PostController::class, 'update']);       // update post
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);   // delete post

    // ❤️ Likes
    Route::post('/posts/{id}/like', [PostController::class, 'toggleLike']);

    // 💬 Comments
    Route::get('/posts/{id}/comments', [PostController::class, 'comments']);
    Route::post('/posts/{id}/comments', [PostController::class, 'addComment']);
    Route::delete('/posts/{postId}/comments/{commentId}', [PostController::class, 'deleteComment']);

    // 📤 Share
    Route::post('/posts/{id}/share', [PostController::class, 'share']);
    // Route::delete('/delete-category/{id}', [App\Http\Controllers\Api\CategoryController::class, 'delete']);

    Route::prefix('buyer')->middleware('auth:sanctum')->group(function () {
        // Browse
        Route::get('categories/{category}/products', [ProductBrowseController::class, 'byCategory']);
        Route::get('product-details/{id}', [ProductBrowseController::class, 'productDetails']);
        // Cart
        //Deliveraddress on the base of sotreId
        Route::get('cart', [CartController::class, 'show']);
        //for adding coupon and discount for a product
        Route::post('cart/apply-coupon', [CartController::class, 'applyCoupon']);
        Route::post('cart/apply-points', [CartController::class, 'applyPoints']);
        Route::post('cart/items', [CartController::class, 'add']);
        Route::post('cart/items/{id}', [CartController::class, 'updateQty']);
        Route::delete('cart/items/{id}', [CartController::class, 'remove']);
        Route::delete('cart/clear', [CartController::class, 'clear']);
        // Checkout
        Route::post('checkout/preview', [CheckoutController::class, 'preview']);
        Route::post('checkout/place', [CheckoutController::class, 'place']);
        // Route::post('checkout')
        Route::post('payment/confirmation', [CheckoutController::class, 'paymentConfirmation']);
        // Orders
        Route::get('orders', [OrderController::class, 'list']);
        Route::get('orders/{orderId}', [OrderController::class, 'detail']);
        Route::post('orders/{storeOrderId}/confirm-delivered', [OrderController::class, 'confirmDelivered']);
        // Reviews
        Route::post('order-items/{orderItem}/review', [ReviewController::class, 'create']);
        Route::get('store/{storeId}/delivery-addresses', [ProductBrowseController::class, 'storeDeliveryAddresses']);
    });
    Route::prefix('buyer')->middleware('auth:sanctum')->group(function () {
        Route::get('addresses', [UserAddressController::class, 'index']);
        Route::post('addresses', [UserAddressController::class, 'store']);
        Route::get('addresses/{id}', [UserAddressController::class, 'show']);
        Route::put('addresses/{id}', [UserAddressController::class, 'update']);
        Route::delete('addresses/{id}', [UserAddressController::class, 'destroy']);

        //get wallet balance
        Route::get('getBalance', [WalletController::class, 'getBalance']);
        //chats
        Route::get('chats', [ChatController::class, 'list']);
        Route::get('chats/{id}/messages', [ChatController::class, 'messages']);
        Route::post('chats/{id}/send', [ChatController::class, 'send']);

        //saved items
        Route::get('saved-items', [SavedItemController::class, 'list']);
        Route::post('saved-items/toggle', [SavedItemController::class, 'toggle']);
        Route::post('saved-items/check', [SavedItemController::class, 'check']);
        Route::get('followed-stores', [StoreFollowController::class, 'list']);
        Route::post('followed-stores/toggle', [StoreFollowController::class, 'toggle']);
        Route::post('followed-stores/check', [StoreFollowController::class, 'check']);

        Route::get('support/tickets', [SupportController::class, 'listTickets']);
        Route::post('support/tickets', [SupportController::class, 'createTicket']);
        Route::get('support/tickets/{id}', [SupportController::class, 'getTicket']);
        Route::post('support/messages', [SupportController::class, 'sendMessage']);
        Route::get('stores', [StoreController::class, 'getAll']);
        Route::get('stores/{id}', [StoreController::class, 'getById']);

        //Start chat with store without order
        Route::post('chats/start/{store_id}', [ChatController::class, 'startChatWithStore']);
        Route::post('chats/start-service/{store_id}', [ChatController::class, 'startChatWithStoreForService']); // it requires service_id in body

        Route::get('stores/{storeId}/reviews', [StoreReviewController::class, 'index']);
        Route::post('stores/{storeId}/reviews', [StoreReviewController::class, 'store'])->middleware('auth:sanctum');
        Route::put('stores/{storeId}/reviews/{reviewId}', [StoreReviewController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('stores/{storeId}/reviews/{reviewId}', [StoreReviewController::class, 'destroy'])->middleware('auth:sanctum');
    });
    Route::prefix('service-categories')->group(function () {
        Route::get('/', [ServiceCategoryController::class, 'index']);
        Route::post('/', [ServiceCategoryController::class, 'store'])->middleware('auth:sanctum');
        Route::get('/{id}', [ServiceCategoryController::class, 'show']);
        Route::put('/{id}', [ServiceCategoryController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [ServiceCategoryController::class, 'destroy'])->middleware('auth:sanctum');

        // ✅ Extra: attach an existing service to a category
        Route::post('/{categoryId}/attach-service/{serviceId}', [ServiceCategoryController::class, 'attachService'])
            ->middleware('auth:sanctum');
    });
    Route::get('service/{categoryId}', [App\Http\Controllers\Api\ServiceController::class, 'relatedServices']);
    Route::get('user/transactions', [TransactionController::class, 'getForAuthUser']);

    Route::prefix('faqs')->group(function () {
        // Public endpoint to get all categories with their faqs
        Route::get('/', [FaqController::class, 'index']);

        // Admin-only CRUD (add middleware('auth:sanctum','can:manage-faqs') if needed)
        Route::get('/category/name/{name}', [FaqController::class, 'showByCategoryName']);

        Route::post('/category', [FaqController::class, 'storeCategory']);
        Route::put('/category/{id}', [FaqController::class, 'updateCategory']);
        Route::delete('/category/{id}', [FaqController::class, 'destroyCategory']);

        Route::post('/', [FaqController::class, 'storeFaq']);
        Route::put('/{id}', [FaqController::class, 'updateFaq']);
        Route::delete('/{id}', [FaqController::class, 'destroyFaq']);


        //escrow
        Route::get('escrow', [EscrowController::class, 'index']);        // total balance + full history
        Route::get('escrow/history', [EscrowController::class, 'history']); // optional paginated history


        Route::post('dispute', [DisputeController::class, 'store']);      // create dispute
        Route::get('dispute', [DisputeController::class, 'myDisputes']); // list my disputes
        Route::get('dispute/{id}', [DisputeController::class, 'show']);   // view single dispute with chat
    });
    Route::get('user-reveiws', [ReviewController::class, 'list']);
    Route::get('my-points', [LoyaltyController::class, 'myPoints']);

    //edit profile 
    Route::post('/auth/edit-profile', [AuthController::class, 'editProfile']);
});
