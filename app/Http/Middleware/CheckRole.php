<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ResponseHelper;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return ResponseHelper::error('Unauthenticated', 401);
        }

        // Check if user has any of the required roles (OR logic)
        if (!$user->hasAnyRole($roles)) {
            return ResponseHelper::error('You do not have the required role to perform this action', 403);
        }

        return $next($request);
    }
}

