<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\BuyerOrderController;
use App\Http\Controllers\Api\Admin\BuyerTransactionController;
use App\Http\Controllers\Api\Admin\SellerUserController;
use App\Http\Controllers\Api\Admin\AdminSellerCreationController;
use App\Http\Controllers\Api\Admin\SellerDetailsController;
use App\Http\Controllers\Api\Admin\SellerOrderController;
use App\Http\Controllers\Api\Admin\SellerChatController;
use App\Http\Controllers\Api\Admin\SellerTransactionController;
use App\Http\Controllers\Api\Admin\SellerSocialFeedController;
use App\Http\Controllers\Api\Admin\SellerProductController;
use App\Http\Controllers\Api\Admin\SellerAnnouncementBannerController;
use App\Http\Controllers\Api\Admin\SellerCouponLoyaltyController;
use App\Http\Controllers\Api\Admin\AdminOrderManagementController;
use App\Http\Controllers\Api\Admin\AdminTransactionManagementController;
use App\Http\Controllers\Api\Admin\AdminStoreKYCController;
use App\Http\Controllers\Api\Admin\AdminSubscriptionController;
use App\Http\Controllers\Api\Admin\AdminPromotionsController;
use App\Http\Controllers\Api\Admin\AdminSocialFeedController;
use App\Http\Controllers\Api\Admin\AdminProductsController;
use App\Http\Controllers\Api\Admin\AdminServicesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminAllUsersController;
use App\Http\Controllers\Api\Admin\AdminBalanceController;
use App\Http\Controllers\Api\Admin\AdminChatsController;
use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AdminLeaderboardController;
use App\Http\Controllers\Api\Admin\AdminSupportController;
use App\Http\Controllers\Api\Admin\AdminDisputeController;
use App\Http\Controllers\Api\Admin\AdminUserManagementController;
use App\Http\Controllers\Api\Admin\AdminFaqController;
use App\Http\Controllers\Api\Admin\AdminKnowledgeBaseController;
use App\Http\Controllers\Api\Admin\AdminTermsController;
use App\Http\Controllers\Api\Admin\AdminSellerHelpRequestController;
use App\Http\Controllers\Api\Admin\AdminWithdrawalRequestController;
use App\Http\Controllers\Api\Admin\AdminPostReportController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\AdminRoleController;

Route::get('admin/banners', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'getAllBanners']);

// Terms route without authentication (public access)
Route::get('/admin/terms', [AdminTermsController::class, 'index']);

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // ========================================
    // DASHBOARD & OVERVIEW MODULE
    // ========================================
    // Get comprehensive dashboard overview with all statistics
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);
    // Get buyer statistics and metrics
    Route::get('/buyer-stats', [AdminDashboardController::class, 'buyerStats']);
    // Get seller statistics and metrics
    Route::get('/seller-stats', [AdminDashboardController::class, 'sellerStats']);
    // Get site-wide statistics and chart data
    Route::get('/site-stats', [AdminDashboardController::class, 'siteStats']);
    // Get latest chats for dashboard
    Route::get('/latest-chats', [AdminDashboardController::class, 'latestChats']);
    // Get latest orders for dashboard
    Route::get('/latest-orders', [AdminDashboardController::class, 'latestOrders']);
    // Filter orders with various criteria
    Route::get('/orders/filter', [AdminDashboardController::class, 'filterOrders']);
    // Bulk actions on orders from dashboard
    Route::post('/orders/bulk-action', [AdminDashboardController::class, 'bulkAction']);

    // ========================================
    // USER MANAGEMENT MODULE (BUYERS)
    // ========================================
    // Get all users with pagination and filtering
    Route::get('/users', [AdminUserController::class, 'index']);
    // Get user statistics and metrics
    Route::get('/users/stats', [AdminUserController::class, 'stats']);
    // Search users by name, email, or phone
    Route::get('/users/search', [AdminUserController::class, 'search']);
    // Bulk actions on users (activate, deactivate, delete)
    Route::post('/users/bulk-action', [AdminUserController::class, 'bulkAction']);
    // Get user profile details
    Route::get('/users/{id}/profile', [AdminUserController::class, 'showProfile']);
    // Get user orders with pagination
    Route::get('/users/{id}/orders', [AdminUserController::class, 'userOrders']);
    // Filter user orders by status, date, etc.
    Route::get('/users/{id}/orders/filter', [AdminUserController::class, 'filterUserOrders']);
    // Bulk actions on user orders
    Route::post('/users/{id}/orders/bulk-action', [AdminUserController::class, 'bulkOrderAction']);
    // Get detailed order information for a user
    Route::get('/users/{id}/orders/{orderId}/details', [AdminUserController::class, 'orderDetails']);
    // Update order status for a user
    Route::put('/users/{id}/orders/{orderId}/status', [AdminUserController::class, 'updateOrderStatus']);
    // Get user chats with pagination
    Route::get('/users/{id}/chats', [AdminUserController::class, 'userChats']);
    // Filter user chats by type, status, etc.
    Route::get('/users/{id}/chats/filter', [AdminUserController::class, 'filterUserChats']);
    // Bulk actions on user chats
    Route::post('/users/{id}/chats/bulk-action', [AdminUserController::class, 'bulkChatAction']);
    // Get detailed chat information
    Route::get('/users/{id}/chats/{chatId}/details', [AdminUserController::class, 'chatDetails']);
    // Send message in a chat
    Route::post('/users/{id}/chats/{chatId}/send', [AdminUserController::class, 'sendMessage']);
    // Get user activities with optional period filter
    Route::get('/users/{id}/activities', [AdminUserController::class, 'userActivities']);
    // Get user transactions with pagination
    Route::get('/users/{id}/transactions', [AdminUserController::class, 'userTransactions']);
    // Filter user transactions by status, type, etc.
    Route::get('/users/{id}/transactions/filter', [AdminUserController::class, 'filterUserTransactions']);
    // Bulk actions on user transactions
    Route::post('/users/{id}/transactions/bulk-action', [AdminUserController::class, 'bulkTransactionAction']);
    // Get detailed transaction information
    Route::get('/users/{id}/transactions/{transactionId}/details', [AdminUserController::class, 'transactionDetails']);
    // Get user posts with pagination
    Route::get('/users/{id}/posts', [AdminUserController::class, 'userPosts']);
    // Filter user posts by visibility, date, etc.
    Route::get('/users/{id}/posts/filter', [AdminUserController::class, 'filterUserPosts']);
    // Bulk actions on user posts
    Route::post('/users/{id}/posts/bulk-action', [AdminUserController::class, 'bulkPostAction']);
    // Get detailed post information
    Route::get('/users/{id}/posts/{postId}/details', [AdminUserController::class, 'postDetails']);
    // Delete a user post
    Route::delete('/users/{id}/posts/{postId}', [AdminUserController::class, 'deletePost']);
    // Get post comments
    Route::get('/users/{id}/posts/{postId}/comments', [AdminUserController::class, 'postComments']);
    // Delete a post comment
    Route::delete('/users/{id}/posts/{postId}/comments/{commentId}', [AdminUserController::class, 'deleteComment']);
    // Get comprehensive user details
    Route::get('/users/{id}/details', [AdminUserController::class, 'userDetails']);
    // Create new user (admin can add users)
    Route::post('/users', [AdminUserController::class, 'create']);
    // Update user information
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    // Delete user account
    Route::delete('/users/{id}', [AdminUserController::class, 'delete']);
    // Top up user wallet (admin can top up for any user)
    Route::post('/users/{id}/wallet/top-up', [AdminUserController::class, 'topUp']);
    // Withdraw from user wallet (admin can withdraw for any user)
    Route::post('/users/{id}/wallet/withdraw', [AdminUserController::class, 'withdraw']);
    // Get user notifications (admin can view any user's notifications)
    Route::get('/users/{id}/notifications', [AdminUserController::class, 'getUserNotifications']);

    // ========================================
    // BUYER ORDER MANAGEMENT MODULE
    // ========================================
    // Get all buyer orders with filtering and pagination
    Route::get('/buyer-orders', [BuyerOrderController::class, 'index']);
    // Filter buyer orders by status, date, store, etc.
    Route::get('/buyer-orders/filter', [BuyerOrderController::class, 'filter']);
    // Bulk actions on buyer orders (update status, mark delivered, etc.)
    Route::post('/buyer-orders/bulk-action', [BuyerOrderController::class, 'bulkAction']);
    // Get detailed order information including products and tracking
    Route::get('/buyer-orders/{orderId}/details', [BuyerOrderController::class, 'orderDetails']);
    // Update order status (pending, processing, shipped, delivered, etc.)
    Route::put('/buyer-orders/{orderId}/status', [BuyerOrderController::class, 'updateOrderStatus']);
    // Get order tracking history
    Route::get('/buyer-orders/{orderId}/tracking', [BuyerOrderController::class, 'orderTracking']);
    // Manually release escrow for a specific store order (admin override)
    Route::post('/buyer-orders/{storeOrderId}/release-escrow', [BuyerOrderController::class, 'releaseEscrow']);

    // ========================================
    // BUYER TRANSACTION MANAGEMENT MODULE
    // ========================================
    // Get all buyer transactions with filtering and pagination
    Route::get('/buyer-transactions', [BuyerTransactionController::class, 'index']);
    // Filter buyer transactions by status, type, date, etc.
    Route::get('/buyer-transactions/filter', [BuyerTransactionController::class, 'filter']);
    // Bulk actions on buyer transactions (update status, mark successful, etc.)
    Route::post('/buyer-transactions/bulk-action', [BuyerTransactionController::class, 'bulkAction']);
    // Get detailed transaction information
    Route::get('/buyer-transactions/{transactionId}/details', [BuyerTransactionController::class, 'transactionDetails']);
    // Update transaction status (pending, successful, failed, cancelled)
    Route::put('/buyer-transactions/{transactionId}/status', [BuyerTransactionController::class, 'updateTransactionStatus']);
    // Get transaction analytics and insights
    Route::get('/buyer-transactions/analytics', [BuyerTransactionController::class, 'analytics']);

    // ========================================
    // SELLER USER MANAGEMENT MODULE
    // ========================================
    // Get all sellers with filtering and pagination
    Route::get('/seller-users', [SellerUserController::class, 'index']);
    // Get seller statistics and metrics
    Route::get('/seller-users/stats', [SellerUserController::class, 'stats']);
    // Search sellers by name, email, store name, etc.
    Route::get('/seller-users/search', [SellerUserController::class, 'search']);
    // Bulk actions on sellers (block, unblock, delete, etc.)
    Route::post('/seller-users/bulk-action', [SellerUserController::class, 'bulkAction']);
    // Get detailed seller information
    Route::get('/seller-users/{id}/details', [SellerUserController::class, 'sellerDetails']);
    // Get seller transactions
    Route::get('/seller-users/{id}/transactions', [SellerUserController::class, 'sellerTransactions']);
    // Toggle block/unblock status for seller
    Route::post('/seller-users/{id}/toggle-block', [SellerUserController::class, 'toggleBlock']);
    // Remove seller account and related data
    Route::delete('/seller-users/{id}/remove', [SellerUserController::class, 'removeSeller']);

    // ========================================
    // ADMIN SELLER CREATION MODULE (3-LEVEL ONBOARDING)
    // ========================================
    // Complete Level 1 onboarding (basic info + profile + categories + social) - Single API call
    Route::post('/create-seller/level1/complete', [AdminSellerCreationController::class, 'level1Complete']);
    // Update Level 1 onboarding (edit basic info + profile + categories + social)
    Route::post('/create-seller/level1/update', [AdminSellerCreationController::class, 'level1Update']);
    // Complete Level 2 onboarding (business details + documents) - Single API call
    Route::post('/create-seller/level2/complete', [AdminSellerCreationController::class, 'level2Complete']);
    // Update Level 2 onboarding (edit business details + documents)
    Route::post('/create-seller/level2/update', [AdminSellerCreationController::class, 'level2Update']);
    // Complete Level 3 onboarding (physical store + utility + address + delivery + theme) - Single API call
    Route::post('/create-seller/level3/complete', [AdminSellerCreationController::class, 'level3Complete']);
    // Update Level 3 onboarding (edit physical store + utility + address + delivery + theme)
    Route::post('/create-seller/level3/update', [AdminSellerCreationController::class, 'level3Update']);

    // Individual Step Routes (Legacy - for backward compatibility)
    // Level 1 - Basic information (name, email, phone, password)
    Route::post('/create-seller/level1/basic', [AdminSellerCreationController::class, 'level1Basic']);
    // Level 1 - Profile and media uploads
    Route::post('/create-seller/level1/profile-media', [AdminSellerCreationController::class, 'level1ProfileMedia']);
    // Level 1 - Categories and social links
    Route::post('/create-seller/level1/categories-social', [AdminSellerCreationController::class, 'level1CategoriesSocial']);
    // Level 2 - Business details (registration, tax info, address)
    Route::post('/create-seller/level2/business-details', [AdminSellerCreationController::class, 'level2BusinessDetails']);
    // Level 2 - Document uploads (certificates, tax clearance)
    Route::post('/create-seller/level2/documents', [AdminSellerCreationController::class, 'level2Documents']);
    // Level 3 - Physical store information
    Route::post('/create-seller/level3/physical-store', [AdminSellerCreationController::class, 'level3PhysicalStore']);
    // Level 3 - Utility bill upload
    Route::post('/create-seller/level3/utility-bill', [AdminSellerCreationController::class, 'level3UtilityBill']);
    // Level 3 - Add store address
    Route::post('/create-seller/level3/add-address', [AdminSellerCreationController::class, 'level3AddAddress']);
    // Level 3 - Add delivery pricing
    Route::post('/create-seller/level3/add-delivery', [AdminSellerCreationController::class, 'level3AddDelivery']);
    // Level 3 - Theme selection
    Route::post('/create-seller/level3/theme', [AdminSellerCreationController::class, 'level3Theme']);

    // Utility Routes
    // Set seller approval status (approved, rejected, pending)
    Route::post('/create-seller/set-approval-status', [AdminSellerCreationController::class, 'setApprovalStatus']);
    // Get seller onboarding progress
    Route::get('/create-seller/progress', [AdminSellerCreationController::class, 'getProgress']);
    // Get all available categories for selection
    Route::get('/create-seller/categories', [AdminSellerCreationController::class, 'getAllCategories']);
    // Reject a specific onboarding field with rejection reason
    Route::post('/create-seller/reject-field', [AdminSellerCreationController::class, 'rejectField']);

    // Store Address Management
    // Get store addresses
    Route::get('/create-seller/addresses', [AdminSellerCreationController::class, 'getStoreAddresses']);
    // Update store address
    Route::put('/create-seller/addresses/{addressId}', [AdminSellerCreationController::class, 'updateStoreAddress']);
    // Delete store address
    Route::delete('/create-seller/addresses/{addressId}', [AdminSellerCreationController::class, 'deleteStoreAddress']);

    // Store Delivery Pricing Management
    // Get store delivery pricing
    Route::get('/create-seller/delivery-pricing', [AdminSellerCreationController::class, 'getStoreDeliveryPricing']);
    // Update store delivery pricing
    Route::put('/create-seller/delivery-pricing/{pricingId}', [AdminSellerCreationController::class, 'updateStoreDeliveryPricing']);
    // Delete store delivery pricing
    Route::delete('/create-seller/delivery-pricing/{pricingId}', [AdminSellerCreationController::class, 'deleteStoreDeliveryPricing']);

    // ========================================
    // SELLER DETAILS MANAGEMENT MODULE
    // ========================================
    // Get comprehensive seller details (user, store, financial info)
    Route::get('/seller-details/{id}', [SellerDetailsController::class, 'getSellerDetails']);
    // Get seller orders with pagination and filtering
    Route::get('/seller-details/{id}/orders', [SellerDetailsController::class, 'getSellerOrders']);
    // Get seller chats with pagination and filtering
    Route::get('/seller-details/{id}/chats', [SellerDetailsController::class, 'getSellerChats']);
    // Get seller transactions with pagination and filtering
    Route::get('/seller-details/{id}/transactions', [SellerDetailsController::class, 'getSellerTransactions']);
    // Get seller social feed posts
    Route::get('/seller-details/{id}/social-feed', [SellerDetailsController::class, 'getSellerSocialFeed']);
    // Get seller products with pagination and filtering
    Route::get('/seller-details/{id}/products', [SellerDetailsController::class, 'getSellerProducts']);
    // Get seller announcements
    Route::get('/seller-details/{id}/announcements', [SellerDetailsController::class, 'getSellerAnnouncements']);
    // Get seller recent activities
    Route::get('/seller-details/{id}/activities', [SellerDetailsController::class, 'getSellerActivities']);
    // Update seller wallet (topup, withdraw)
    Route::post('/seller-details/{id}/wallet', [SellerDetailsController::class, 'updateSellerWallet']);
    // Toggle seller block/unblock status
    Route::post('/seller-details/{id}/toggle-block', [SellerDetailsController::class, 'toggleSellerBlock']);
    // Delete seller account and related data
    Route::delete('/seller-details/{id}', [SellerDetailsController::class, 'deleteSeller']);

    // ========================================
    // SELLER ORDER MANAGEMENT MODULE
    // ========================================
    // Get all orders for a specific seller with pagination and filtering
    Route::get('/seller-orders/{userId}', [SellerOrderController::class, 'getSellerOrders']);
    // Get order statistics for a seller
    Route::get('/seller-orders/{userId}/statistics', [SellerOrderController::class, 'getOrderStatistics']);
    // Get detailed order information for a seller
    Route::get('/seller-orders/{userId}/{storeOrderId}/details', [SellerOrderController::class, 'getOrderDetails']);
    // Update order status for a seller
    Route::put('/seller-orders/{userId}/{storeOrderId}/status', [SellerOrderController::class, 'updateOrderStatus']);
    // Mark order as out for delivery with delivery code
    Route::post('/seller-orders/{userId}/{storeOrderId}/out-for-delivery', [SellerOrderController::class, 'markOutForDelivery']);
    // Verify delivery code for order completion
    Route::post('/seller-orders/{userId}/{storeOrderId}/verify-delivery', [SellerOrderController::class, 'verifyDeliveryCode']);

    // ========================================
    // SELLER CHAT MANAGEMENT MODULE
    // ========================================
    // Get all chats for a specific seller with pagination and filtering
    Route::get('/seller-chats/{userId}', [SellerChatController::class, 'getSellerChats']);
    // Get chat statistics for a seller
    Route::get('/seller-chats/{userId}/statistics', [SellerChatController::class, 'getChatStatistics']);
    // Get detailed chat information
    Route::get('/seller-chats/{userId}/{chatId}/details', [SellerChatController::class, 'getChatDetails']);
    // Send message in a chat
    Route::post('/seller-chats/{userId}/{chatId}/send-message', [SellerChatController::class, 'sendMessage']);
    // Mark messages as read in a chat
    Route::post('/seller-chats/{userId}/{chatId}/mark-read', [SellerChatController::class, 'markAsRead']);
    // Create new chat for a seller
    Route::post('/seller-chats/{userId}/create', [SellerChatController::class, 'createChat']);
    // Delete chat
    Route::delete('/seller-chats/{userId}/{chatId}', [SellerChatController::class, 'deleteChat']);

    // ========================================
    // SELLER TRANSACTION MANAGEMENT MODULE
    // ========================================
    // Get all transactions for a specific seller with pagination and filtering
    Route::get('/seller-transactions/{userId}', [SellerTransactionController::class, 'getSellerTransactions']);
    // Get transaction statistics for a seller
    Route::get('/seller-transactions/{userId}/statistics', [SellerTransactionController::class, 'getTransactionStatistics']);
    // Get detailed transaction information
    Route::get('/seller-transactions/{userId}/{transactionId}/details', [SellerTransactionController::class, 'getTransactionDetails']);
    // Update transaction status
    Route::put('/seller-transactions/{userId}/{transactionId}/status', [SellerTransactionController::class, 'updateTransactionStatus']);
    // Download transaction receipt
    Route::get('/seller-transactions/{userId}/{transactionId}/receipt', [SellerTransactionController::class, 'downloadTransactionReceipt']);

    // ========================================
    // SELLER SOCIAL FEED MANAGEMENT MODULE
    // ========================================
    // Get all posts for a specific seller with pagination and filtering
    Route::get('/seller-social-feed/{userId}', [SellerSocialFeedController::class, 'getSellerPosts']);
    // Get social feed statistics for a seller
    Route::get('/seller-social-feed/{userId}/statistics', [SellerSocialFeedController::class, 'getSocialFeedStatistics']);
    // Get detailed post information
    Route::get('/seller-social-feed/{userId}/{postId}/details', [SellerSocialFeedController::class, 'getPostDetails']);
    // Get post comments
    Route::get('/seller-social-feed/{userId}/{postId}/comments', [SellerSocialFeedController::class, 'getPostComments']);
    // Update post visibility (public, private, friends)
    Route::put('/seller-social-feed/{userId}/{postId}/visibility', [SellerSocialFeedController::class, 'updatePostVisibility']);
    // Delete post
    Route::delete('/seller-social-feed/{userId}/{postId}', [SellerSocialFeedController::class, 'deletePost']);
    // Delete post comment
    Route::delete('/seller-social-feed/{userId}/{postId}/{commentId}', [SellerSocialFeedController::class, 'deleteComment']);
    // Get post engagement analytics
    Route::get('/seller-social-feed/{userId}/analytics', [SellerSocialFeedController::class, 'getPostEngagementAnalytics']);

    // ========================================
    // SELLER PRODUCT MANAGEMENT MODULE
    // ========================================
    // Get all products for a specific seller with pagination and filtering
    Route::get('/seller-products/{userId}', [SellerProductController::class, 'getSellerProducts']);
    // Get product statistics for a seller
    Route::get('/seller-products/{userId}/statistics', [SellerProductController::class, 'getProductStatistics']);
    // Get detailed product information
    Route::get('/seller-products/{userId}/{productId}/details', [SellerProductController::class, 'getProductDetails']);
    // Get product statistics and analytics
    Route::get('/seller-products/{userId}/{productId}/stats', [SellerProductController::class, 'getProductStats']);
    // Create new product for a seller (admin can add products for sellers)
    Route::post('/seller-products/{userId}', [SellerProductController::class, 'createProduct']);
    // Update product information - POST for file uploads
    Route::post('/seller-products/{userId}/{productId}', [SellerProductController::class, 'updateProduct']);
    // Update product status (active, inactive, sold, unavailable)
    Route::put('/seller-products/{userId}/{productId}/status', [SellerProductController::class, 'updateProductStatus']);
    // Update product quantity
    Route::put('/seller-products/{userId}/{productId}/quantity', [SellerProductController::class, 'updateProductQuantity']);
    // Boost product for better visibility
    Route::post('/seller-products/{userId}/{productId}/boost', [SellerProductController::class, 'boostProduct']);
    // Mark product as sold
    Route::post('/seller-products/{userId}/{productId}/mark-sold', [SellerProductController::class, 'markProductAsSold']);
    // Mark product as unavailable
    Route::post('/seller-products/{userId}/{productId}/mark-unavailable', [SellerProductController::class, 'markProductAsUnavailable']);
    // Mark product as available
    Route::post('/seller-products/{userId}/{productId}/mark-available', [SellerProductController::class, 'markProductAsAvailable']);
    // Delete product
    Route::delete('/seller-products/{userId}/{productId}', [SellerProductController::class, 'deleteProduct']);
    // Get product analytics and insights
    Route::get('/seller-products/{userId}/analytics', [SellerProductController::class, 'getProductAnalytics']);

    // ========================================
    // SELLER ANNOUNCEMENTS & BANNERS MANAGEMENT MODULE
    // ========================================
    // Get all announcements for a specific seller with pagination
    Route::get('/seller-announcements/{userId}', [SellerAnnouncementBannerController::class, 'getSellerAnnouncements']);
    // Get detailed announcement information
    Route::get('/seller-announcements/{userId}/{announcementId}/details', [SellerAnnouncementBannerController::class, 'getAnnouncementDetails']);
    // Create new announcement for a seller
    Route::post('/seller-announcements/{userId}', [SellerAnnouncementBannerController::class, 'createAnnouncement']);
    // Update announcement message
    Route::put('/seller-announcements/{userId}/{announcementId}', [SellerAnnouncementBannerController::class, 'updateAnnouncement']);
    // Delete announcement
    Route::delete('/seller-announcements/{userId}/{announcementId}', [SellerAnnouncementBannerController::class, 'deleteAnnouncement']);
    // Bulk actions on announcements (delete, activate, deactivate)
    Route::post('/seller-announcements/{userId}/bulk-action', [SellerAnnouncementBannerController::class, 'bulkActionAnnouncements']);

    // Get all banners for a specific seller with pagination
    Route::get('/seller-banners/{userId}', [SellerAnnouncementBannerController::class, 'getSellerBanners']);
    // Get detailed banner information
    Route::get('/seller-banners/{userId}/{bannerId}/details', [SellerAnnouncementBannerController::class, 'getBannerDetails']);
    // Create new banner for a seller
    Route::post('/seller-banners/{userId}', [SellerAnnouncementBannerController::class, 'createBanner']);
    // Update banner (image and/or link) - POST for file uploads
    Route::post('/seller-banners/{userId}/{bannerId}', [SellerAnnouncementBannerController::class, 'updateBanner']);
    // Delete banner
    Route::delete('/seller-banners/{userId}/{bannerId}', [SellerAnnouncementBannerController::class, 'deleteBanner']);
    // Bulk actions on banners (delete, activate, deactivate)
    Route::post('/seller-banners/{userId}/bulk-action', [SellerAnnouncementBannerController::class, 'bulkActionBanners']);

    // Get combined statistics for announcements and banners
    Route::get('/seller-announcements-banners/{userId}/statistics', [SellerAnnouncementBannerController::class, 'getStatistics']);

    // ========================================
    // SELLER COUPONS & LOYALTY MANAGEMENT MODULE
    // ========================================
    // Get all coupons for a specific seller with pagination
    Route::get('/seller-coupons/{userId}', [SellerCouponLoyaltyController::class, 'getSellerCoupons']);
    // Get detailed coupon information
    Route::get('/seller-coupons/{userId}/{couponId}/details', [SellerCouponLoyaltyController::class, 'getCouponDetails']);
    // Create new coupon for a seller
    Route::post('/seller-coupons/{userId}', [SellerCouponLoyaltyController::class, 'createCoupon']);
    // Update coupon (code, discount, usage limits, expiry)
    Route::put('/seller-coupons/{userId}/{couponId}', [SellerCouponLoyaltyController::class, 'updateCoupon']);
    // Delete coupon
    Route::delete('/seller-coupons/{userId}/{couponId}', [SellerCouponLoyaltyController::class, 'deleteCoupon']);
    // Bulk actions on coupons (delete, activate, deactivate)
    Route::post('/seller-coupons/{userId}/bulk-action', [SellerCouponLoyaltyController::class, 'bulkActionCoupons']);
    // Get coupon analytics and usage insights
    Route::get('/seller-coupons/{userId}/analytics', [SellerCouponLoyaltyController::class, 'getCouponAnalytics']);

    // Get loyalty settings for a seller
    Route::get('/seller-loyalty-settings/{userId}', [SellerCouponLoyaltyController::class, 'getLoyaltySettings']);
    // Update loyalty settings (points per order/referral, enable/disable)
    Route::put('/seller-loyalty-settings/{userId}', [SellerCouponLoyaltyController::class, 'updateLoyaltySettings']);
    // Get customer loyalty points for a seller
    Route::get('/seller-loyalty-customers/{userId}', [SellerCouponLoyaltyController::class, 'getCustomerPoints']);

    // Get combined statistics for coupons and loyalty points
    Route::get('/seller-coupons-loyalty/{userId}/statistics', [SellerCouponLoyaltyController::class, 'getStatistics']);

    // ========================================
    // ORDER MANAGEMENT MODULE
    // ========================================
    // Get all orders with filtering and pagination
    Route::get('/orders', [AdminOrderManagementController::class, 'getAllOrders']);
    // Get detailed order information including products and tracking
    Route::get('/orders/{storeOrderId}/details', [AdminOrderManagementController::class, 'getOrderDetails']);
    // Admin accepts order on behalf of seller (sets delivery fee & delivery details)
    Route::post('/orders/{storeOrderId}/accept', [AdminOrderManagementController::class, 'acceptOrderOnBehalf']);
    // Update order status (pending, processing, shipped, out_for_delivery, delivered, completed, disputed, cancelled)
    Route::put('/orders/{storeOrderId}/status', [AdminOrderManagementController::class, 'updateOrderStatus']);
    // Get order tracking history
    Route::get('/orders/{storeOrderId}/tracking', [AdminOrderManagementController::class, 'getOrderTracking']);
    // Bulk actions on orders (update_status, mark_delivered, mark_completed)
    Route::post('/orders/bulk-action', [AdminOrderManagementController::class, 'bulkAction']);
    // Get order statistics and analytics
    Route::get('/orders/statistics', [AdminOrderManagementController::class, 'getOrderStatistics']);

    // ========================================
    // TRANSACTION MANAGEMENT MODULE
    // ========================================
    // Get all transactions with filtering and pagination
    Route::get('/transactions', [AdminTransactionManagementController::class, 'getAllTransactions']);
    // Get detailed transaction information
    Route::get('/transactions/{transactionId}/details', [AdminTransactionManagementController::class, 'getTransactionDetails']);
    // Update transaction status (pending, successful, failed, cancelled)
    Route::put('/transactions/{transactionId}/status', [AdminTransactionManagementController::class, 'updateTransactionStatus']);
    // Bulk actions on transactions (update_status, mark_successful, mark_failed)
    Route::post('/transactions/bulk-action', [AdminTransactionManagementController::class, 'bulkAction']);
    // Get transaction statistics and analytics
    Route::get('/transactions/statistics', [AdminTransactionManagementController::class, 'getTransactionStatistics']);
    // Get transaction analytics with date filtering
    Route::get('/transactions/analytics', [AdminTransactionManagementController::class, 'getTransactionAnalytics']);

    // ========================================
    // STORE KYC & DETAILS MANAGEMENT MODULE
    // ========================================
    // Get all stores with KYC status and filtering
    Route::get('/stores', [AdminStoreKYCController::class, 'getAllStores']);
    // Get detailed store information with all levels (Level 1, 2, 3 data)
    Route::get('/stores/{storeId}/details', [AdminStoreKYCController::class, 'getStoreDetails']);
    // Hard delete a store and its major related data (orders, products, services, chats, etc.)
    Route::delete('/stores/{storeId}/hard-delete', [AdminStoreKYCController::class, 'hardDeleteStore']);
    // Update store KYC status (pending, approved, rejected) and send notification
    Route::put('/stores/{storeId}/kyc-status', [AdminStoreKYCController::class, 'updateStoreStatus']);
    // Update store onboarding level (1, 2, 3) and send notification
    Route::put('/stores/{storeId}/level', [AdminStoreKYCController::class, 'updateStoreLevel']);
    // Bulk actions on stores (update_kyc_status, update_level, activate, deactivate)
    Route::post('/stores/bulk-action', [AdminStoreKYCController::class, 'bulkAction']);
    // Get KYC statistics and trends
    Route::get('/stores/kyc-statistics', [AdminStoreKYCController::class, 'getKYCStatistics']);
    // Assign or unassign account officer to a store (Super Admin only)
    Route::put('/stores/{storeId}/assign-account-officer', [AdminStoreKYCController::class, 'assignAccountOfficer']);

    // ========================================
    // ACCOUNT OFFICER VENDORS MODULE
    // ========================================
    // Get all account officers with vendor counts (Super Admin only)
    Route::get('/account-officers', [\App\Http\Controllers\Api\Admin\AccountOfficerController::class, 'index']);
    // Get dashboard stats for current Account Officer
    Route::get('/account-officers/me/dashboard', [\App\Http\Controllers\Api\Admin\AccountOfficerController::class, 'myDashboard']);
    // Get vendors assigned to a specific account officer
    Route::get('/account-officers/{id}/vendors', [\App\Http\Controllers\Api\Admin\AccountOfficerController::class, 'getVendors']);
    // Get vendors assigned to current user (Account Officer)
    Route::get('/vendors/assigned-to-me', [\App\Http\Controllers\Api\Admin\AccountOfficerController::class, 'myVendors']);

    // ========================================
    // SUBSCRIPTION MANAGEMENT MODULE
    // ========================================
    // Get all subscriptions with filtering and pagination
    Route::get('/subscriptions', [AdminSubscriptionController::class, 'getAllSubscriptions']);
    // Get subscription details
    Route::get('/subscriptions/{subscriptionId}/details', [AdminSubscriptionController::class, 'getSubscriptionDetails']);
    // Update subscription status (active, expired, cancelled, suspended)
    Route::put('/subscriptions/{subscriptionId}/status', [AdminSubscriptionController::class, 'updateSubscriptionStatus']);
    // Bulk actions on subscriptions (update_status, activate, deactivate)
    Route::post('/subscriptions/bulk-action', [AdminSubscriptionController::class, 'bulkAction']);
    // Get subscription statistics and analytics
    Route::get('/subscriptions/statistics', [AdminSubscriptionController::class, 'getSubscriptionStatistics']);

    // ========================================
    // SUBSCRIPTION PLANS MANAGEMENT
    // ========================================
    // Get all subscription plans
    Route::get('/subscription-plans', [AdminSubscriptionController::class, 'getAllPlans']);
    // Create new subscription plan
    Route::post('/subscription-plans', [AdminSubscriptionController::class, 'createPlan']);
    // Update subscription plan
    Route::put('/subscription-plans/{planId}', [AdminSubscriptionController::class, 'updatePlan']);
    // Delete subscription plan (only if no active subscriptions)
    Route::delete('/subscription-plans/{planId}', [AdminSubscriptionController::class, 'deletePlan']);

    // ========================================
    // PROMOTIONS MANAGEMENT MODULE (BOOSTED PRODUCTS)
    // ========================================
    // Get all boosted products (promotions) with filtering and pagination
    Route::get('/promotions', [AdminPromotionsController::class, 'getAllPromotions']);
    // Get detailed promotion information including performance metrics
    Route::get('/promotions/{promotionId}/details', [AdminPromotionsController::class, 'getPromotionDetails']);
    // Update promotion details (general edit route - budget, duration, location, start_date, status, payment info)
    Route::put('/promotions/{promotionId}', [AdminPromotionsController::class, 'updatePromotion']);
    // Update promotion status (approve, reject, stop, extend)
    Route::put('/promotions/{promotionId}/status', [AdminPromotionsController::class, 'updatePromotionStatus']);
    // Extend promotion duration and budget
    Route::post('/promotions/{promotionId}/extend', [AdminPromotionsController::class, 'extendPromotion']);
    // Bulk actions on promotions (approve, reject, stop, extend)
    Route::post('/promotions/bulk-action', [AdminPromotionsController::class, 'bulkAction']);
    // Get promotion statistics and analytics
    Route::get('/promotions/statistics', [AdminPromotionsController::class, 'getPromotionStatistics']);

    // ========================================
    // SOCIAL FEED MANAGEMENT MODULE
    // ========================================
    // Get all social feed posts with filtering and pagination
    Route::get('/social-feed', [AdminSocialFeedController::class, 'getAllPosts']);
    // Get detailed post information with engagement metrics
    Route::get('/social-feed/{postId}/details', [AdminSocialFeedController::class, 'getPostDetails']);
    // Update post visibility (public, private, friends)
    Route::put('/social-feed/{postId}/visibility', [AdminSocialFeedController::class, 'updatePostVisibility']);
    // Delete post and all related data
    Route::delete('/social-feed/{postId}', [AdminSocialFeedController::class, 'deletePost']);
    // Get post comments with pagination
    Route::get('/social-feed/{postId}/comments', [AdminSocialFeedController::class, 'getPostComments']);
    // Delete post comment
    Route::delete('/social-feed/{postId}/comments/{commentId}', [AdminSocialFeedController::class, 'deleteComment']);
    // Get social feed statistics and trends
    Route::get('/social-feed/statistics', [AdminSocialFeedController::class, 'getSocialFeedStatistics']);
    // Get top performing posts
    Route::get('/social-feed/top-posts', [AdminSocialFeedController::class, 'getTopPosts']);

    // ========================================
    // PRODUCTS MANAGEMENT MODULE
    // ========================================
    // Get all products with filtering and pagination
    Route::get('/products', [AdminProductsController::class, 'getAllProducts']);
    // Get detailed product information with reviews, stats, and boost info
    Route::get('/products/{productId}/details', [AdminProductsController::class, 'getProductDetails']);
    // Update product status (active, featured)
    Route::put('/products/{productId}/status', [AdminProductsController::class, 'updateProductStatus']);
    // Boost product for better visibility
    Route::post('/products/{productId}/boost', [AdminProductsController::class, 'boostProduct']);
    // Update product information
    Route::put('/products/{productId}', [AdminProductsController::class, 'updateProduct']);
    // Delete product and all related data
    Route::delete('/products/{productId}', [AdminProductsController::class, 'deleteProduct']);
    // Get product analytics and insights
    Route::get('/products/analytics', [AdminProductsController::class, 'getProductAnalytics']);

    // ========================================
    // SERVICE CATEGORIES MANAGEMENT MODULE
    // ========================================
    // Get all service categories with pagination
    Route::get('/service-categories', [\App\Http\Controllers\Api\ServiceCategoryController::class, 'index']);
    // Get single service category details
    Route::get('/service-categories/{id}', [\App\Http\Controllers\Api\ServiceCategoryController::class, 'show']);
    // Create new service category
    Route::post('/service-categories', [\App\Http\Controllers\Api\ServiceCategoryController::class, 'store']);
    // Update service category
    Route::put('/service-categories/{id}', [\App\Http\Controllers\Api\ServiceCategoryController::class, 'update']);
    // Delete service category
    Route::delete('/service-categories/{id}', [\App\Http\Controllers\Api\ServiceCategoryController::class, 'destroy']);

    // ========================================
    // SERVICES MANAGEMENT MODULE
    // ========================================
    // Get all services with filtering and pagination
    Route::get('/services', [AdminServicesController::class, 'getAllServices']);
    // Get detailed service information with stats and sub-services
    Route::get('/services/{serviceId}/details', [AdminServicesController::class, 'getServiceDetails']);
    // Approve service (set status to active and notify seller)
    Route::post('/services/{serviceId}/approve', [AdminServicesController::class, 'approveService']);
    // Update service status (active, inactive, sold, unavailable) - can include rejection_reason
    Route::put('/services/{serviceId}/status', [AdminServicesController::class, 'updateServiceStatus']);
    // Update service information (edit service details)
    Route::post('/services/{serviceId}', [AdminServicesController::class, 'updateService']);
    // Delete service and all related data
    Route::delete('/services/{serviceId}', [AdminServicesController::class, 'deleteService']);
    // Get service analytics and insights
    Route::get('/services/analytics', [AdminServicesController::class, 'getServiceAnalytics']);

    // ========================================
    // SELLER SERVICES MANAGEMENT MODULE
    // ========================================
    // Get all services for a specific seller with pagination and filtering
    Route::get('/seller-services/{sellerId}', [AdminServicesController::class, 'getSellerServices']);
    // Update seller service information
    Route::put('/seller-services/{sellerId}/{serviceId}', [AdminServicesController::class, 'updateSellerService']);
    // Delete seller service
    Route::delete('/seller-services/{sellerId}/{serviceId}', [AdminServicesController::class, 'deleteSellerService']);

    // ========================================
    // GENERAL ADMIN MODULES
    // ========================================
    // Import controllers for general modules


    // ========================================
    // ALL USERS MANAGEMENT MODULE
    // ========================================
    // Get all users with filtering and pagination
    Route::get('/all-users', [AdminAllUsersController::class, 'getAllUsers']);
    // Get detailed user information including wallet, orders, transactions
    Route::get('/all-users/{userId}/details', [AdminAllUsersController::class, 'getUserDetails']);
    // Get user saved addresses
    Route::get('/all-users/{userId}/addresses', [AdminAllUsersController::class, 'getUserAddresses']);
    // Update user status (active, inactive) and disabled flag
    Route::put('/all-users/{userId}/status', [AdminAllUsersController::class, 'updateUserStatus']);
    // Get user analytics and trends
    Route::get('/all-users/analytics', [AdminAllUsersController::class, 'getUserAnalytics']);

    // Create new user (admin can add users)
    Route::post('/all-users', [AdminAllUsersController::class, 'createUser']);
    // Update user details (admin can edit user information) - POST for file uploads
    Route::post('/all-users/{userId}', [AdminAllUsersController::class, 'updateUser']);
    // Delete user (admin can remove users)
    Route::delete('/all-users/{userId}', [AdminAllUsersController::class, 'deleteUser']);

    // ========================================
    // BALANCE MANAGEMENT MODULE
    // ========================================
    // Get all user balances with filtering and pagination
    Route::get('/balances', [AdminBalanceController::class, 'getAllBalances']);
    // Debug: Get all users without pagination
    Route::get('/balances/debug', [AdminBalanceController::class, 'getAllUsersDebug']);
    // Get detailed user balance information including transactions
    Route::get('/balances/{userId}/details', [AdminBalanceController::class, 'getUserBalanceDetails']);
    // Update user wallet balance (add, subtract, set)
    Route::put('/balances/{userId}/update', [AdminBalanceController::class, 'updateUserBalance']);
    // Get balance analytics and trends
    Route::get('/balances/analytics', [AdminBalanceController::class, 'getBalanceAnalytics']);

    // ========================================
    // CHATS MANAGEMENT MODULE
    // ========================================
    // Get all chats with filtering and pagination
    Route::get('/chats', [AdminChatsController::class, 'getAllChats']);
    // Get detailed chat information including messages
    Route::get('/chats/{chatId}/details', [AdminChatsController::class, 'getChatDetails']);
    // Send message to chat
    Route::post('/chats/{chatId}/send-message', [AdminChatsController::class, 'sendMessage']);
    // Mark chat as read
    Route::put('/chats/{chatId}/mark-read', [AdminChatsController::class, 'markChatAsRead']);
    // Update chat status (open, closed, resolved)
    Route::put('/chats/{chatId}/status', [AdminChatsController::class, 'updateChatStatus']);
    // Delete chat and all messages
    Route::delete('/chats/{chatId}', [AdminChatsController::class, 'deleteChat']);
    // Get chat analytics and trends
    Route::get('/chats/analytics', [AdminChatsController::class, 'getChatAnalytics']);

    // ========================================
    // ANALYTICS DASHBOARD MODULE
    // ========================================
    // Get comprehensive analytics dashboard data
    Route::get('/analytics/dashboard', [AdminAnalyticsController::class, 'getAnalyticsDashboard']);
    // Get user analytics and trends
    Route::get('/analytics/users', [AdminAnalyticsController::class, 'getUserAnalytics']);
    // Get revenue analytics and trends
    Route::get('/analytics/revenue', [AdminAnalyticsController::class, 'getRevenueAnalytics']);
    // Get product analytics and trends
    Route::get('/analytics/products', [AdminAnalyticsController::class, 'getProductAnalytics']);

    // ========================================
    // LEADERBOARD MODULE
    // ========================================
    // Get comprehensive leaderboard data (today, weekly, monthly, all)
    Route::get('/leaderboard', [AdminLeaderboardController::class, 'getLeaderboard']);
    // Get top performing stores by revenue
    Route::get('/leaderboard/top-revenue', [AdminLeaderboardController::class, 'getTopStoresByRevenue']);
    // Get top performing stores by orders
    Route::get('/leaderboard/top-orders', [AdminLeaderboardController::class, 'getTopStoresByOrders']);
    // Get top performing stores by followers
    Route::get('/leaderboard/top-followers', [AdminLeaderboardController::class, 'getTopStoresByFollowers']);
    // Get leaderboard analytics and trends
    Route::get('/leaderboard/analytics', [AdminLeaderboardController::class, 'getLeaderboardAnalytics']);

    // ========================================
    // SUPPORT & TICKETS MODULE
    // ========================================
    // Get all support tickets with filtering and pagination
    Route::get('/support/tickets', [AdminSupportController::class, 'getAllTickets']);
    // Get detailed ticket information including messages
    Route::get('/support/tickets/{ticketId}/details', [AdminSupportController::class, 'getTicketDetails']);
    // Reply to ticket
    Route::post('/support/tickets/{ticketId}/reply', [AdminSupportController::class, 'replyToTicket']);
    // Update ticket status (pending, in_progress, resolved, closed)
    Route::put('/support/tickets/{ticketId}/status', [AdminSupportController::class, 'updateTicketStatus']);
    // Mark ticket as resolved
    Route::put('/support/tickets/{ticketId}/resolve', [AdminSupportController::class, 'resolveTicket']);
    // Close ticket
    Route::put('/support/tickets/{ticketId}/close', [AdminSupportController::class, 'closeTicket']);
    // Delete ticket and all messages
    Route::delete('/support/tickets/{ticketId}', [AdminSupportController::class, 'deleteTicket']);
    // Get support analytics and trends
    Route::get('/support/analytics', [AdminSupportController::class, 'getSupportAnalytics']);

    // ========================================
    // RATINGS & REVIEWS MODULE
    // ========================================
    // Summary cards: totals and averages
    Route::get('/ratings-reviews/summary', [\App\Http\Controllers\Api\Admin\AdminRatingsReviewsController::class, 'summary']);
    // List product reviews with filters and pagination
    Route::get('/ratings-reviews/products', [\App\Http\Controllers\Api\Admin\AdminRatingsReviewsController::class, 'listProductReviews']);
    // Product review details
    Route::get('/ratings-reviews/products/{reviewId}/details', [\App\Http\Controllers\Api\Admin\AdminRatingsReviewsController::class, 'productReviewDetails']);
    // Delete product review
    Route::delete('/ratings-reviews/products/{reviewId}', [\App\Http\Controllers\Api\Admin\AdminRatingsReviewsController::class, 'deleteProductReview']);
    // List store reviews with filters and pagination
    Route::get('/ratings-reviews/stores', [\App\Http\Controllers\Api\Admin\AdminRatingsReviewsController::class, 'listStoreReviews']);
    // Store review details
    Route::get('/ratings-reviews/stores/{reviewId}/details', [\App\Http\Controllers\Api\Admin\AdminRatingsReviewsController::class, 'storeReviewDetails']);
    // Delete store review
    Route::delete('/ratings-reviews/stores/{reviewId}', [\App\Http\Controllers\Api\Admin\AdminRatingsReviewsController::class, 'deleteStoreReview']);

    // ========================================
    // REFERRAL MANAGEMENT ROUTES
    // ========================================

    // Get referral dashboard with statistics and referrers list
    Route::get('/referrals', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'getReferralDashboard']);

    // Get users referred by a specific referrer
    Route::get('/referrals/{userId}/referred-users', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'getReferredUsers']);

    // Get detailed referral information for a specific user
    Route::get('/referrals/{userId}/details', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'getReferralDetails']);

    // Get referral settings (bonus amounts, percentages, etc.)
    Route::get('/referrals/settings', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'getReferralSettings']);

    // Update referral settings
    Route::post('/referrals/settings', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'updateReferralSettings']);

    // Get referral analytics and trends
    Route::get('/referrals/analytics', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'getReferralAnalytics']);

    // Update individual referral status
    Route::put('/referrals/{referralId}/status', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'updateReferralStatus']);

    // Bulk update referral status
    Route::put('/referrals/bulk-status', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'bulkUpdateReferralStatus']);

    // Get referral FAQs
    Route::get('/referrals/faqs', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'getReferralFaqs']);

    // Create referral FAQ
    Route::post('/referrals/faqs', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'createReferralFaq']);

    // Update referral FAQ
    Route::put('/referrals/faqs/{faqId}', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'updateReferralFaq']);

    // Delete referral FAQ
    Route::delete('/referrals/faqs/{faqId}', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'deleteReferralFaq']);

    // Export referral data
    Route::get('/referrals/export', [\App\Http\Controllers\Api\Admin\AdminReferralController::class, 'exportReferralData']);

    // ==================== System Push Notifications ====================

    // Get all notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'getAllNotifications']);

    // Create notification
    Route::post('/notifications', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'createNotification']);

    // Get notification details
    Route::get('/notifications/{id}', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'getNotificationDetails']);

    // Update notification status
    Route::put('/notifications/{id}/status', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'updateNotificationStatus']);

    // Delete notification
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'deleteNotification']);

    // Get users for audience selection
    Route::get('/notifications/audience/users', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'getUsersForAudience']);

    // Get audience data with buyers and sellers arrays
    Route::get('/notifications/audience/data', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'getAudienceData']);

    // ==================== System Banners ====================

    // Get all banners

    // Create banner
    Route::post('/banners', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'createBanner']);

    // Get active banners
    Route::get('/banners/active', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'getActiveBanners']);

    // Get banner details
    Route::get('/banners/{id}', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'getBannerDetails']);

    // Update banner
    Route::post('/banners/{id}', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'updateBanner']);

    // Delete banner
    Route::delete('/banners/{id}', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'deleteBanner']);

    // Toggle banner status
    Route::put('/banners/{id}/toggle-status', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'toggleBannerStatus']);

    // Get banner analytics
    Route::get('/banners/analytics', [\App\Http\Controllers\Api\Admin\AdminBannerController::class, 'getBannerAnalytics']);

    // ==================== Admin Product & Service Management ====================

    // Get all stores (name, picture, id only)
    Route::get('/stores', [\App\Http\Controllers\Api\Admin\AdminProductServiceController::class, 'getStores']);
    Route::post('/stores-delete/{storeId}', [\App\Http\Controllers\Api\Admin\AdminProductServiceController::class, 'deleteStore']);

    // Create product for any store (admin)
    Route::post('/products', [\App\Http\Controllers\Api\Admin\AdminProductServiceController::class, 'createProduct']);

    // Create service for any store (admin)
    Route::post('/services', [\App\Http\Controllers\Api\Admin\AdminProductServiceController::class, 'createService']);

    // ========================================
    // DISPUTE MANAGEMENT MODULE
    // ========================================
    // Get all disputes with filtering and pagination
    Route::get('/disputes', [AdminDisputeController::class, 'getAllDisputes']);
    // Get dispute statistics and metrics
    Route::get('/disputes/statistics', [AdminDisputeController::class, 'getDisputeStatistics']);
    // Get detailed dispute information including chat and order details
    Route::get('/disputes/{disputeId}/details', [AdminDisputeController::class, 'getDisputeDetails'])->where('disputeId', '[0-9]+');
    // Update dispute status (pending, on_hold, resolved, closed)
    Route::put('/disputes/{disputeId}/status', [AdminDisputeController::class, 'updateDisputeStatus'])->where('disputeId', '[0-9]+');
    // Resolve dispute with resolution notes and winner
    Route::post('/disputes/{disputeId}/resolve', [AdminDisputeController::class, 'resolveDispute'])->where('disputeId', '[0-9]+');
    // Close dispute
    Route::post('/disputes/{disputeId}/close', [AdminDisputeController::class, 'closeDispute'])->where('disputeId', '[0-9]+');
    // Bulk actions on disputes (update_status, resolve, close)
    Route::post('/disputes/bulk-action', [AdminDisputeController::class, 'bulkAction']);
    // Get dispute analytics and trends
    Route::get('/disputes/analytics', [AdminDisputeController::class, 'getDisputeAnalytics']);
    // Dispute chat management
    Route::get('/disputes/{disputeId}/chat', [AdminDisputeController::class, 'getDisputeChatMessages'])->where('disputeId', '[0-9]+'); // get dispute chat messages
    Route::post('/disputes/{disputeId}/message', [AdminDisputeController::class, 'sendMessage'])->where('disputeId', '[0-9]+'); // send message in dispute chat

    // ========================================
    // ADMIN USER MANAGEMENT MODULE
    // ========================================
    // Get all admin users with filtering and pagination
    Route::get('/admin-users', [AdminUserManagementController::class, 'index']);
    // Get admin user statistics and metrics
    Route::get('/admin-users/stats', [AdminUserManagementController::class, 'stats']);
    // Search admin users by name, email, or phone
    Route::get('/admin-users/search', [AdminUserManagementController::class, 'search']);
    // Bulk actions on admin users (activate, deactivate, delete)
    Route::post('/admin-users/bulk-action', [AdminUserManagementController::class, 'bulkAction']);
    // Get admin user profile details
    Route::get('/admin-users/{id}/profile', [AdminUserManagementController::class, 'showProfile']);
    // Get comprehensive admin user details
    Route::get('/admin-users/{id}/details', [AdminUserManagementController::class, 'userDetails']);
    // Create new admin user
    Route::post('/admin-users', [AdminUserManagementController::class, 'create']);
    // Update admin user information
    Route::put('/admin-users/{id}', [AdminUserManagementController::class, 'update']);
    // Delete admin user account
    Route::delete('/admin-users/{id}', [AdminUserManagementController::class, 'delete']);

    // ========================================
    // FAQ MANAGEMENT MODULE
    // ========================================
    // Get all FAQ categories
    Route::get('/faq/categories', [AdminFaqController::class, 'getCategories']);
    // Get FAQ statistics
    Route::get('/faq/statistics', [AdminFaqController::class, 'getFaqStatistics']);
    // Get FAQs by category (general, buyer, seller)
    Route::get('/faq/general', [AdminFaqController::class, 'getGeneralFaqs']);
    Route::get('/faq/buyer', [AdminFaqController::class, 'getBuyerFaqs']);
    Route::get('/faq/seller', [AdminFaqController::class, 'getSellerFaqs']);
    // Get FAQ details
    Route::get('/faq/{id}/details', [AdminFaqController::class, 'getFaqDetails']);
    // Create new FAQ
    Route::post('/faq', [AdminFaqController::class, 'createFaq']);
    // Update FAQ
    Route::put('/faq/{id}', [AdminFaqController::class, 'updateFaq']);
    // Delete FAQ
    Route::delete('/faq/{id}', [AdminFaqController::class, 'deleteFaq']);
    // Bulk actions on FAQs (activate, deactivate, delete)
    Route::post('/faq/bulk-action', [AdminFaqController::class, 'bulkAction']);
    // Update FAQ category
    Route::put('/faq/categories/{id}', [AdminFaqController::class, 'updateCategory']);

    // ========================================
    // KNOWLEDGE BASE MANAGEMENT MODULE
    // ========================================
    // Get all knowledge base items with filtering
    Route::get('/knowledge-base', [AdminKnowledgeBaseController::class, 'index']);
    // Get single knowledge base item
    Route::get('/knowledge-base/{id}', [AdminKnowledgeBaseController::class, 'show']);
    // Create new knowledge base item
    Route::post('/knowledge-base', [AdminKnowledgeBaseController::class, 'store']);
    // Update knowledge base item
    Route::put('/knowledge-base/{id}', [AdminKnowledgeBaseController::class, 'update']);
    // Delete knowledge base item
    Route::delete('/knowledge-base/{id}', [AdminKnowledgeBaseController::class, 'destroy']);
    // Toggle active status
    Route::post('/knowledge-base/{id}/toggle-status', [AdminKnowledgeBaseController::class, 'toggleStatus']);

    // ========================================
    // TERMS & POLICIES MANAGEMENT MODULE
    // ========================================
    // Update terms and policies (requires auth)
    Route::put('/terms', [AdminTermsController::class, 'update']);

    // ========================================
    // SELLER HELP REQUESTS (LISTING)
    // ========================================
    Route::get('/seller-help/requests', [AdminSellerHelpRequestController::class, 'index']);

    // ========================================
    // WITHDRAWAL REQUESTS MANAGEMENT
    // ========================================
    // Get all withdrawal requests with filtering
    Route::get('/withdrawal-requests', [AdminWithdrawalRequestController::class, 'index']);
    // Get single withdrawal request details
    Route::get('/withdrawal-requests/{id}', [AdminWithdrawalRequestController::class, 'show']);
    // Approve withdrawal request
    Route::post('/withdrawal-requests/{id}/approve', [AdminWithdrawalRequestController::class, 'approve']);
    // Reject withdrawal request (refunds amount back to user)
    Route::post('/withdrawal-requests/{id}/reject', [AdminWithdrawalRequestController::class, 'reject']);

    // ========================================
    // POST REPORTS MANAGEMENT
    // ========================================
    // Get all post reports with filtering
    Route::get('/post-reports', [AdminPostReportController::class, 'index']);
    // Get single post report details
    Route::get('/post-reports/{id}', [AdminPostReportController::class, 'show']);
    // Update report status (reviewed, resolved, dismissed)
    Route::put('/post-reports/{id}/status', [AdminPostReportController::class, 'updateStatus']);
    // Delete post report
    Route::delete('/post-reports/{id}', [AdminPostReportController::class, 'destroy']);

    // ========================================
    // ADMIN SOCIAL POST MANAGEMENT (ADDED AT END)
    // ========================================
    // Create social post for a store (admin can create posts for stores)
    Route::post('/stores/{storeId}/posts', [AdminSocialFeedController::class, 'createPostForStore']);

    // ========================================
    // ADMIN USER ADDRESS MANAGEMENT (ADDED AT END)
    // ========================================
    // Add address for a user (admin can add addresses for users)
    Route::post('/all-users/{userId}/addresses', [AdminAllUsersController::class, 'addUserAddress']);

    // ========================================
    // RBAC - ROLE & PERMISSION MANAGEMENT
    // ========================================
    Route::prefix('rbac')->group(function () {
        // Roles
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::post('/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);
        Route::get('/roles/{id}/permissions', [RoleController::class, 'getPermissions']);

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{id}', [PermissionController::class, 'show']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{id}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
        Route::get('/permissions/module/{module}', [PermissionController::class, 'getByModule']);

        // User Role Assignment
        Route::get('/users/{userId}/roles', [AdminRoleController::class, 'getUserRoles']);
        Route::post('/users/{userId}/roles', [AdminRoleController::class, 'assignRole']);
        Route::delete('/users/{userId}/roles/{roleId}', [AdminRoleController::class, 'revokeRole']);
        Route::get('/users/{userId}/permissions', [AdminRoleController::class, 'getUserPermissions']);
        Route::post('/users/{userId}/check-permission', [AdminRoleController::class, 'checkPermission']);

        // Get current user's permissions (for frontend)
        Route::get('/me/permissions', [AdminRoleController::class, 'getMyPermissions']);

        // Modules (read-only config endpoint)
        Route::get('/modules', [PermissionController::class, 'getModules']);
    });
});
