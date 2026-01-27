<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ResponseHelper;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return ResponseHelper::error('Unauthenticated', 401);
        }

        // Check if user has any of the required permissions (OR logic)
        if (!$user->hasAnyPermission($permissions)) {
            return ResponseHelper::error('You do not have permission to perform this action', 403);
        }

        return $next($request);
    }
}

