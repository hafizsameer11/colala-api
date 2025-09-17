<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/un-auth',function(){
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    // Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
});