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
    // Complete Level 2 onboarding (business details + documents) - Single API call
    Route::post('/create-seller/level2/complete', [AdminSellerCreationController::class, 'level2Complete']);
    // Complete Level 3 onboarding (physical store + utility + address + delivery + theme) - Single API call
    Route::post('/create-seller/level3/complete', [AdminSellerCreationController::class, 'level3Complete']);
    
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
    // Update product information
    Route::put('/seller-products/{userId}/{productId}', [SellerProductController::class, 'updateProduct']);
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
    // Update banner (image and/or link)
    Route::put('/seller-banners/{userId}/{bannerId}', [SellerAnnouncementBannerController::class, 'updateBanner']);
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
    // Update store KYC status (pending, approved, rejected) and send notification
    Route::put('/stores/{storeId}/kyc-status', [AdminStoreKYCController::class, 'updateStoreStatus']);
    // Update store onboarding level (1, 2, 3) and send notification
    Route::put('/stores/{storeId}/level', [AdminStoreKYCController::class, 'updateStoreLevel']);
    // Bulk actions on stores (update_kyc_status, update_level, activate, deactivate)
    Route::post('/stores/bulk-action', [AdminStoreKYCController::class, 'bulkAction']);
    // Get KYC statistics and trends
    Route::get('/stores/kyc-statistics', [AdminStoreKYCController::class, 'getKYCStatistics']);

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
    // SERVICES MANAGEMENT MODULE
    // ========================================
    // Get all services with filtering and pagination
    Route::get('/services', [AdminServicesController::class, 'getAllServices']);
    // Get detailed service information with stats and sub-services
    Route::get('/services/{serviceId}/details', [AdminServicesController::class, 'getServiceDetails']);
    // Update service status (active, inactive, sold, unavailable)
    Route::put('/services/{serviceId}/status', [AdminServicesController::class, 'updateServiceStatus']);
    // Update service information
    Route::put('/services/{serviceId}', [AdminServicesController::class, 'updateService']);
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
    // Update user status (active, inactive)
    Route::put('/all-users/{userId}/status', [AdminAllUsersController::class, 'updateUserStatus']);
    // Get user analytics and trends
    Route::get('/all-users/analytics', [AdminAllUsersController::class, 'getUserAnalytics']);

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
});