<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'store.access' => \App\Http\Middleware\StoreAccessMiddleware::class,
        ]);
        
        // Track user activity - only runs if user is authenticated (checks Auth::check() internally)
        $middleware->web(append: [
            \App\Http\Middleware\TrackUserActivity::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\TrackUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
