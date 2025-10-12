<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);
    
    // Buyer Statistics
    Route::get('/buyer-stats', [AdminDashboardController::class, 'buyerStats']);
    
    // Seller Statistics  
    Route::get('/seller-stats', [AdminDashboardController::class, 'sellerStats']);
    
    // Site Statistics (Chart Data)
    Route::get('/site-stats', [AdminDashboardController::class, 'siteStats']);
    
    // Latest Chats
    Route::get('/latest-chats', [AdminDashboardController::class, 'latestChats']);
    
    // Latest Orders
    Route::get('/latest-orders', [AdminDashboardController::class, 'latestOrders']);
    Route::get('/orders/filter', [AdminDashboardController::class, 'filterOrders']);
    Route::post('/orders/bulk-action', [AdminDashboardController::class, 'bulkAction']);
});
