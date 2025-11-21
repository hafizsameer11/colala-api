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
use App\Http\Controllers\Api\PostReportController;
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
use App\Http\Controllers\Buyer\PhoneRequestController;
use App\Http\Controllers\Api\Buyer\BuyerOrderPaymentController;
use App\Http\Controllers\Api\Buyer\BuyerKnowledgeBaseController;
use App\Http\Controllers\Api\Buyer\BuyerTermsController;
use App\Http\Controllers\Api\Seller\SellerStoreSettingsController;
use App\Http\Controllers\Api\UserActivityController;
use App\Http\Controllers\WebhookController;

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
Route::get('buyer/product/referral-products', [ProductController::class, 'getReferralProducts']);
Route::get('buyer/product/vip-products', [ProductController::class, 'getVipProducts']);
Route::get('buyer/categories/{category}/products', [ProductBrowseController::class, 'byCategory']);
Route::get('buyer/product-details/{id}', [ProductBrowseController::class, 'productDetails']);
Route::get('buyer/products/top-selling', [ProductBrowseController::class, 'topSelling']);

// Stores
Route::prefix('buyer')->group(function () {
    Route::get('stores', [StoreController::class, 'getAll']);
    Route::get('stores/{id}', [StoreController::class, 'getById'])->middleware('auth:sanctum');
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
Route::post('/search/by-image', [ImageSearchController::class, 'search']);
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

// Flutterwave Webhook (PUBLIC - No authentication required)
Route::post('flutterwave/webhook', [WebhookController::class, 'flutterwave']);


// ==================== PROTECTED ROUTES (AUTH REQUIRED) ====================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('buyer/product/referral-products', [ProductController::class, 'getReferralProducts']);

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
    Route::post('/posts/{id}/report', [PostReportController::class, 'report']);

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
        Route::get('stores/{storeId}/has-ordered', [OrderController::class, 'hasOrderedFromStore'])->where('storeId', '[0-9]+');
        
        // Order Payment (Post-Acceptance)
        Route::get('orders/{orderId}/payment-info', [BuyerOrderPaymentController::class, 'getPaymentInfo']);
        Route::post('orders/{orderId}/pay', [BuyerOrderPaymentController::class, 'processPayment']);
        Route::post('orders/{orderId}/cancel', [BuyerOrderPaymentController::class, 'cancelOrder']);

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
        Route::get('chat/unread-count', [ChatController::class, 'unreadCount']);

        // Phone Number Requests
        Route::post('phone-request', [PhoneRequestController::class, 'requestPhoneNumber']);
        Route::get('phone-request/status', [PhoneRequestController::class, 'checkPhoneRequestStatus']);
        Route::get('phone-request/revealed', [PhoneRequestController::class, 'getRevealedPhoneNumbers']);
        Route::get('settings/phone-visibility/{sellerId}', [SellerStoreSettingsController::class, 'getPhoneVisibilityBySellerId']);

        // Wallet
        Route::get('getBalance', [WalletController::class, 'getBalance']);

        // Knowledge Base
        Route::get('knowledge-base', [BuyerKnowledgeBaseController::class, 'index']);
        Route::get('knowledge-base/{id}', [BuyerKnowledgeBaseController::class, 'show']);

        // Terms & Policies
        Route::get('terms', [BuyerTermsController::class, 'index']);
        Route::get('terms/{type}', [BuyerTermsController::class, 'show']);

        // User Activity (Heartbeat & Online Status)
        Route::post('activity/heartbeat', [UserActivityController::class, 'heartbeat']);
        Route::get('activity/status', [UserActivityController::class, 'getMyStatus']);
    });
    Route::get('users/{userId}/status', [UserActivityController::class, 'getUserStatus']);

    // ---------- USER PROFILE ----------
    Route::post('/auth/edit-profile', [AuthController::class, 'editProfile']);
    Route::get('/auth/plan', [AuthController::class, 'getPlan']);

    // ---------- WALLET ----------
    Route::post('wallet/withdraw', [WalletWithdrawalController::class, 'requestWithdraw']);
    Route::post('wallet/withdraw/auto', [WalletWithdrawalController::class, 'automaticWithdraw']);
    Route::get('wallet/withdraw/banks', [WalletWithdrawalController::class, 'getBanks']);
    Route::post('wallet/withdraw/validate-account', [WalletWithdrawalController::class, 'validateAccount']);
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
    // Route::get('escrow', [EscrowController::class, 'index']);
    // Route::get('escrow/history', [EscrowController::class, 'history']);
    // Route::post('dispute', [DisputeController::class, 'store']);
    // Route::get('dispute', [DisputeController::class, 'myDisputes']);
    // Route::get('dispute/{id}', [DisputeController::class, 'show']);
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


        // Dispute Management
    });
    Route::post('dispute', [DisputeController::class, 'store']);              // create dispute
    Route::get('dispute', [DisputeController::class, 'myDisputes']);         // list my disputes
    Route::get('dispute/{id}', [DisputeController::class, 'show'])->where('id', '[0-9]+');           // view single dispute with chat
    Route::post('dispute/{id}/message', [DisputeController::class, 'sendMessage'])->where('id', '[0-9]+');  // send message in dispute chat
    Route::post('dispute/{id}/mark-read', [DisputeController::class, 'markAsRead'])->where('id', '[0-9]+'); // mark messages as read

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

/*
|--------------------------------------------------------------------------
| 🛍️ NEW ORDER FLOW - BUYER ROUTES (Separate Orders Per Store)
|--------------------------------------------------------------------------
|
| FLOW SUMMARY:
| 1. Buyer adds items to cart from multiple stores
| 2. Buyer previews checkout → sees total per store
| 3. Buyer places order → creates SEPARATE orders (one per store)
| 4. Each store accepts/rejects independently
| 5. Buyer pays accepted orders (wallet OR card via Flutterwave)
| 6. Track order until delivery
|
| KEY ROUTES:
|
| === CHECKOUT & ORDER PLACEMENT ===
| ✅ POST /api/buyer/checkout/preview - Preview order with all stores
|    Body: { "delivery_address_id": 1, "payment_method": "card" }
|    Returns: { items_total, shipping_total, platform_fee, grand_total, stores: [...] }
|
| ✅ POST /api/buyer/checkout/place - Place order (creates separate orders per store)
|    Body: { "delivery_address_id": 1, "payment_method": "card" }
|    Returns: {
|      "message": "3 order(s) created successfully",
|      "total_orders": 3,
|      "orders": [...]
|    }
|
| === PAYMENT ===
| ✅ GET /api/buyer/orders/{orderId}/payment-info - Get payment details for an order
|    Returns: { order_no, amount_to_pay, store: {...}, status, can_pay }
|
| ✅ POST /api/buyer/orders/{orderId}/pay - Pay with WALLET
|    Body: {} (no body needed, uses user's wallet)
|    Returns: { "message": "Payment successful" }
|
| ✅ POST /api/buyer/payment/confirmation - Confirm CARD payment (after Flutterwave)
|    Body: { "order_id": 1, "tx_id": "FLW-12345", "amount": 11150.00 }
|    Returns: { "message": "Payment confirmed successfully" }
|
| ✅ POST /api/buyer/orders/{orderId}/cancel - Cancel unpaid order
|    Body: { "reason": "Changed my mind" }
|    Returns: { "message": "Order cancelled successfully" }
|
| === ORDER MANAGEMENT ===
| ✅ GET /api/buyer/orders - Get all buyer orders
| ✅ GET /api/buyer/orders/{id} - Get order details
|
| IMPORTANT NOTES:
| - Each order is for ONE store only (no more multi-store orders)
| - Order must be accepted by seller before payment
| - Payment creates escrow automatically
| - Wallet payment is immediate (POST /orders/{id}/pay)
| - Card payment requires Flutterwave flow:
|   1. Frontend initializes Flutterwave
|   2. User completes payment
|   3. Frontend calls /payment/confirmation
| - Each order has its own order_no, grand_total, and payment_status
| - Delivery fee is set by seller during acceptance
|
| EXAMPLE FLOW:
|
| 1. CHECKOUT:
|    POST /api/buyer/checkout/preview
|    {
|      "delivery_address_id": 1,
|      "payment_method": "card"
|    }
|
|    Response:
|    {
|      "items_total": 45000,
|      "shipping_total": 0,
|      "platform_fee": 675,
|      "grand_total": 45675,
|      "stores": [
|        { "store_id": 5, "items_subtotal": 10000, ... },
|        { "store_id": 8, "items_subtotal": 15000, ... },
|        { "store_id": 12, "items_subtotal": 20000, ... }
|      ]
|    }
|
| 2. PLACE ORDER:
|    POST /api/buyer/checkout/place
|    {
|      "delivery_address_id": 1,
|      "payment_method": "card"
|    }
|
|    Response:
|    {
|      "message": "3 order(s) created successfully",
|      "total_orders": 3,
|      "orders": [
|        { "id": 1, "order_no": "COL-20251028-123456", ... },
|        { "id": 2, "order_no": "COL-20251028-123457", ... },
|        { "id": 3, "order_no": "COL-20251028-123458", ... }
|      ]
|    }
|
| 3. WAIT FOR SELLER ACCEPTANCE:
|    (Sellers accept/reject on their end)
|    (You'll receive notifications when orders are accepted/rejected)
|
| 4A. PAY WITH WALLET:
|    POST /api/buyer/orders/1/pay
|    {}
|
|    Response:
|    { "message": "Payment successful" }
|
| 4B. PAY WITH CARD (Flutterwave):
|    Step 1: Get payment info
|    GET /api/buyer/orders/1/payment-info
|
|    Response:
|    {
|      "order_no": "COL-20251028-123456",
|      "amount_to_pay": 11150.00,
|      "store": { "store_name": "Tech Store", ... },
|      "can_pay": true
|    }
|
|    Step 2: Initialize Flutterwave on frontend
|    (Use amount_to_pay from response)
|
|    Step 3: After successful Flutterwave payment
|    POST /api/buyer/payment/confirmation
|    {
|      "order_id": 1,
|      "tx_id": "FLW-12345678",
|      "amount": 11150.00
|    }
|
|    Response:
|    { "message": "Payment confirmed successfully" }
|
| 5. TRACK ORDER:
|    GET /api/buyer/orders/1
|
|    Response:
|    {
|      "id": 1,
|      "order_no": "COL-20251028-123456",
|      "status": "paid",
|      "payment_status": "paid",
|      "grand_total": 11150.00,
|      "storeOrders": [{
|        "status": "processing",
|        "estimated_delivery_date": "2025-11-10",
|        ...
|      }]
|    }
|
| KEY DIFFERENCES FROM OLD FLOW:
| ✅ Multiple orders created (one per store) instead of one order
| ✅ Each order paid separately
| ✅ Can use different payment methods for different orders
| ✅ Clearer tracking (one order = one store)
| ✅ Simpler payment flow
| ✅ No partial payments confusion
|
| See: SEPARATE_ORDERS_PER_STORE_GUIDE.md for full documentation
|
*/
