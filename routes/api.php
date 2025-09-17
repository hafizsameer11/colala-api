<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

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
// Route::get('/migrate/rollback', function () {
//     Artisan::call('migrate:rollback');
//     return response()->json(['message' => 'Migration rollback successfully'], 200);
// });

Route::get('/un-auth',function(){
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});