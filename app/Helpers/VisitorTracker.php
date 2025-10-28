<?php

namespace App\Helpers;

use App\Models\{StoreVisitor, User, Product};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class VisitorTracker
{
    /**
     * Track a store profile visit
     */
    public static function trackStoreVisit(int $storeId, Request $request = null): void
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return; // Don't track unauthenticated visits
            }

            $request = $request ?? request();

            StoreVisitor::create([
                'store_id' => $storeId,
                'user_id' => $user->id,
                'product_id' => null,
                'visit_type' => 'store',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to not disrupt the main flow
            Log::warning('Failed to track store visit: ' . $e->getMessage());
        }
    }

    /**
     * Track a product visit
     */
    public static function trackProductVisit(int $productId, Request $request = null): void
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return; // Don't track unauthenticated visits
            }

            $product = Product::with('store')->find($productId);
            if (!$product || !$product->store) {
                return;
            }

            $request = $request ?? request();

            StoreVisitor::create([
                'store_id' => $product->store_id,
                'user_id' => $user->id,
                'product_id' => $productId,
                'visit_type' => 'product',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to not disrupt the main flow
            Log::warning('Failed to track product visit: ' . $e->getMessage());
        }
    }
}

