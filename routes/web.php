<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/flutterwave-payment.html', function () {
    return view('flutterwave-payment');
});
