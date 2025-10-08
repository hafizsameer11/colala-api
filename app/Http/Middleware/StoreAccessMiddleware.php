<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\StoreUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = null): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Get store ID from route parameter
        $storeId = $request->route('storeId') ?? $request->route('store');
        
        if (!$storeId) {
            return response()->json([
                'status' => false,
                'message' => 'Store ID is required'
            ], 400);
        }

        // Check if user has access to the store
        $storeUser = StoreUser::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->first();

        if (!$storeUser) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have access to this store'
            ], 403);
        }

        // Check specific permission if provided
        if ($permission && !$storeUser->hasPermission($permission)) {
            return response()->json([
                'status' => false,
                'message' => "You do not have permission to {$permission}"
            ], 403);
        }

        // Add store user info to request for use in controllers
        $request->merge([
            'store_user' => $storeUser,
            'store_role' => $storeUser->role,
            'store_permissions' => $storeUser->getAllPermissions()
        ]);

        return $next($request);
    }
}
