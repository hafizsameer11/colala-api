<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ResponseHelper;

class EnsureBuyerOnly
{
    /**
     * Handle an incoming request.
     * Allows public access OR buyer access, but blocks admins and sellers
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is authenticated, check if they're a buyer
        if ($user) {
            // Block admins
            if ($user->role === 'admin' || $user->role === 'super_admin' || $user->role === 'account_officer') {
                return ResponseHelper::error('This endpoint is only accessible to buyers', 403);
            }

            // Block sellers (users with stores)
            if ($user->role === 'seller' || $user->store) {
                return ResponseHelper::error('This endpoint is only accessible to buyers', 403);
            }

            // Allow buyers (role='buyer' OR role IS NULL OR role='')
            // This is already handled by the above checks
        }

        // Allow public access (unauthenticated users) or buyers
        return $next($request);
    }
}

