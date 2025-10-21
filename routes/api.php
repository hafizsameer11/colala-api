<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\Buyer\CartController;
use App\Http\Controllers\Api\Buyer\CheckoutController;
use App\Http\Controllers\Api\Buyer\OrderController;
use App\Http\Controllers\Api\Buyer\ProductBrowseController;
use App\Http\Controllers\Api\Buyer\ReviewController;
use App\Http\Controllers\Api\Buyer\UserAddressController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StoreReviewController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\SellerLeaderboardController;
use App\Http\Controllers\Api\Admin\AdminBannerController;
use App\Http\Controllers\Api\WalletWithdrawalController;
use App\Http\Controllers\Api\ImageSearchController;
use App\Http\Controllers\Api\CameraSearchController;
use App\Http\Controllers\Api\ProductImageSearchController;
use App\Http\Controllers\Api\SimpleStoreUserController;
use App\Http\Controllers\Buyer\ChatController;
use App\Http\Controllers\Buyer\SavedItemController;
use App\Http\Controllers\Buyer\StoreFollowController;
use App\Http\Controllers\Buyer\LoyaltyController;
use App\Http\Controllers\Buyer\DisputeController;
use App\Http\Controllers\Api\Buyer\EscrowController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserNotificationController;

require __DIR__ . '/seller.php';
require __DIR__ . '/admin.php';


// ==================== SYSTEM & MAINTENANCE ====================
Route::get('/optimize-app', function () {
    Artisan::call('optimize:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    Artisan::call('view:cache');
    Artisan::call('optimize');

    return "Application optimized and caches cleared successfully!";
});

Route::get('/migrate', function () {
    Artisan::call('migrate');
    return response()->json(['message' => 'Migration successful'], 200);
});

Route::get('/un-auth', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');


// ==================== AUTH (REGISTER / LOGIN / OTP) ====================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/guest-token', [AuthController::class, 'generateGuestToken']);
});


// ==================== PUBLIC ROUTES (NO AUTH REQUIRED) ====================

// Categories
Route::get('/categories', [CategoryController::class, 'getAll']);

// Brands
Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'getAll']);
});

// Products (Buyer)
Route::get('buyer/product/get-all', [ProductController::class, 'getAllforBuyer']);
Route::get('buyer/categories/{category}/products', [ProductBrowseController::class, 'byCategory']);
Route::get('buyer/product-details/{id}', [ProductBrowseController::class, 'productDetails']);
Route::get('buyer/products/top-selling', [ProductBrowseController::class, 'topSelling']);

// Stores
Route::prefix('buyer')->group(function () {
    Route::get('stores', [StoreController::class, 'getAll']);
    Route::get('stores/{id}', [StoreController::class, 'getById']);
    Route::get('stores/{storeId}/reviews', [StoreReviewController::class, 'index']);
});

// Service Categories
Route::prefix('service-categories')->group(function () {
    Route::get('/', [ServiceCategoryController::class, 'index']);
    Route::get('/{id}', [ServiceCategoryController::class, 'show']);
});
Route::get('service/{categoryId}', [ServiceController::class, 'relatedServices']);

// Search
Route::get('/search', [SearchController::class, 'search']);
Route::post('/search/camera', [ImageSearchController::class, 'search']);
Route::post('/search/barcode', [CameraSearchController::class, 'searchByBarcode']);
Route::post('/search/image-exact', [ProductImageSearchController::class, 'search']);

// Posts (public listing)
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{id}', [PostController::class, 'show']);
Route::get('/posts/{id}/comments', [PostController::class, 'comments']);

// FAQs
Route::prefix('faqs')->group(function () {
    Route::get('/', [FaqController::class, 'index']);
    Route::get('/category/name/{name}', [FaqController::class, 'showByCategoryName']);
});

// Banners
Route::get('/banners/active', [AdminBannerController::class, 'getActiveBanners']);


// ==================== PROTECTED ROUTES (AUTH REQUIRED) ====================
Route::middleware('auth:sanctum')->group(function () {

    // ---------- CATEGORY (ADMIN) ----------
    Route::post('/create-category', [CategoryController::class, 'create']);
    Route::post('/update-category/{id}', [CategoryController::class, 'update']);

    // ---------- BRANDS (ADMIN) ----------
    Route::prefix('brands')->group(function () {
        Route::post('/', [BrandController::class, 'create']);
        Route::put('{id}', [BrandController::class, 'update']);
        Route::delete('{id}', [BrandController::class, 'delete']);
    });

    // ---------- POSTS (CREATE/UPDATE/DELETE/LIKE/COMMENT) ----------
    Route::post('/posts', [PostController::class, 'store']);
    Route::post('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    Route::post('/posts/{id}/like', [PostController::class, 'toggleLike']);
    Route::post('/posts/{id}/comments', [PostController::class, 'addComment']);
    Route::delete('/posts/{postId}/comments/{commentId}', [PostController::class, 'deleteComment']);
    Route::post('/posts/{id}/share', [PostController::class, 'share']);

    // ---------- BUYER ----------
    Route::prefix('buyer')->group(function () {
        // Cart
        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/apply-coupon', [CartController::class, 'applyCoupon']);
        Route::post('cart/apply-points', [CartController::class, 'applyPoints']);
        Route::post('cart/items', [CartController::class, 'add']);
        Route::post('cart/items/{id}', [CartController::class, 'updateQty']);
        Route::get('cart-quantity', [CartController::class, 'cartQuantity']);
        Route::delete('cart/items/{id}', [CartController::class, 'remove']);
        Route::delete('cart/clear', [CartController::class, 'clear']);

        // Checkout
        Route::post('checkout/preview', [CheckoutController::class, 'preview']);
        Route::post('checkout/place', [CheckoutController::class, 'place']);
        Route::post('payment/confirmation', [CheckoutController::class, 'paymentConfirmation']);

        // Orders
        Route::get('orders', [OrderController::class, 'list']);
        Route::get('orders/{orderId}', [OrderController::class, 'detail']);
        Route::post('orders/{storeOrderId}/confirm-delivered', [OrderController::class, 'confirmDelivered']);

        // Reviews
        Route::post('order-items/{orderItem}/review', [ReviewController::class, 'create']);
        Route::post('stores/{storeId}/reviews', [StoreReviewController::class, 'store']);
        Route::put('stores/{storeId}/reviews/{reviewId}', [StoreReviewController::class, 'update']);
        Route::delete('stores/{storeId}/reviews/{reviewId}', [StoreReviewController::class, 'destroy']);

        // Addresses
        Route::get('addresses', [UserAddressController::class, 'index']);
        Route::post('addresses', [UserAddressController::class, 'store']);
        Route::get('addresses/{id}', [UserAddressController::class, 'show']);
        Route::put('addresses/{id}', [UserAddressController::class, 'update']);
        Route::delete('addresses/{id}', [UserAddressController::class, 'destroy']);

        // Store access
        Route::get('store', [SimpleStoreUserController::class, 'getUserStore']);

        // Support
        Route::get('support/tickets', [SupportController::class, 'listTickets']);
        Route::post('support/tickets', [SupportController::class, 'createTicket']);
        Route::get('support/tickets/{id}', [SupportController::class, 'getTicket']);
        Route::post('support/messages', [SupportController::class, 'sendMessage']);

        // Saved items
        Route::get('saved-items', [SavedItemController::class, 'list']);
        Route::post('saved-items/toggle', [SavedItemController::class, 'toggle']);
        Route::post('saved-items/check', [SavedItemController::class, 'check']);

        // Followed stores
        Route::get('followed-stores', [StoreFollowController::class, 'list']);
        Route::post('followed-stores/toggle', [StoreFollowController::class, 'toggle']);
        Route::post('followed-stores/check', [StoreFollowController::class, 'check']);

        // Chats
        Route::get('chats', [ChatController::class, 'list']);
        Route::get('chats/{id}/messages', [ChatController::class, 'messages']);
        Route::post('chats/{id}/send', [ChatController::class, 'send']);
        Route::post('chats/start/{store_id}', [ChatController::class, 'startChatWithStore']);
        Route::post('chats/start-service/{store_id}', [ChatController::class, 'startChatWithStoreForService']);

        // Wallet
        Route::get('getBalance', [WalletController::class, 'getBalance']);
    });

    // ---------- USER PROFILE ----------
    Route::post('/auth/edit-profile', [AuthController::class, 'editProfile']);

    // ---------- WALLET ----------
    Route::post('wallet/withdraw', [WalletWithdrawalController::class, 'requestWithdraw']);
    Route::post('wallet/withdraw/referral', [WalletWithdrawalController::class, 'requestReferralWithdraw']);
    Route::get('wallet/withdrawals', [WalletWithdrawalController::class, 'myWithdrawals']);
    Route::post('wallet/top-up', [WalletController::class, 'topUp']);
    Route::get('wallet/refferal-balance', [WalletController::class, 'refferalBalance']);
    Route::post('wallet/transfer', [WalletController::class, 'transfer']);

    // ---------- USER DATA ----------
    Route::get('user/transactions', [TransactionController::class, 'getForAuthUser']);
    Route::get('user-reveiws', [ReviewController::class, 'list']);
    Route::get('my-points', [LoyaltyController::class, 'myPoints']);
    Route::get('leaderboard/sellers', [SellerLeaderboardController::class, 'index']);

    // ---------- ESCROW / DISPUTE ----------
    Route::get('escrow', [EscrowController::class, 'index']);
    Route::get('escrow/history', [EscrowController::class, 'history']);
    Route::post('dispute', [DisputeController::class, 'store']);
    Route::get('dispute', [DisputeController::class, 'myDisputes']);
    Route::get('dispute/{id}', [DisputeController::class, 'show']);

    // ---------- NOTIFICATIONS ----------
    Route::get('notifications', [NotificationController::class, 'getForUser']);
    Route::post('notifications/mark-as-read/{id}', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications', [UserNotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [UserNotificationController::class, 'markAsRead']);
    Route::put('/notifications/mark-all-read', [UserNotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [UserNotificationController::class, 'delete']);
    Route::get('/notifications/stats', [UserNotificationController::class, 'stats']);

    // ---------- BANNERS TRACKING ----------
    Route::post('/banners/{id}/view', [AdminBannerController::class, 'trackBannerView']);
    Route::post('/banners/{id}/click', [AdminBannerController::class, 'trackBannerClick']);
});

