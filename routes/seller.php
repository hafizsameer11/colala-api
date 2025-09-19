<?php

use App\Http\Controllers\Api\SellerRegistrationController;
use Illuminate\Routing\Route;

Route::prefix('seller')->group(function () {
    Route::post('register/step1', [SellerRegistrationController::class, 'registerStep1']);
    Route::post('register/{store}/step2', [SellerRegistrationController::class, 'registerStep2']);
    Route::post('register/{store}/step3', [SellerRegistrationController::class, 'registerStep3']);
});
