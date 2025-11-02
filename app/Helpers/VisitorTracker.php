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
            $request = $request ?? request();

            // Only track if user is authenticated (since user_id is required in migration)
            if (!$user) {
                Log::info('Store visit not tracked - user not authenticated', [
                    'store_id' => $storeId,
                    'ip' => $request->ip()
                ]);
                return;
            }

            StoreVisitor::create([
                'store_id' => $storeId,
                'user_id' => $user->id,
                'product_id' => null,
                'visit_type' => 'store',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::debug('Store visit tracked successfully', [
                'store_id' => $storeId,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            // Log error but don't disrupt the main flow
            Log::error('Failed to track store visit: ' . $e->getMessage(), [
                'store_id' => $storeId,
                'user_id' => Auth::id(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Track a product visit
     */
    public static function trackProductVisit(int $productId, Request $request = null): void
    {
        try {
            $user = Auth::user();
            $request = $request ?? request();

            // Only track if user is authenticated (since user_id is required in migration)
            if (!$user) {
                Log::info('Product visit not tracked - user not authenticated', [
                    'product_id' => $productId,
                    'ip' => $request->ip()
                ]);
                return;
            }

            $product = Product::with('store')->find($productId);
            if (!$product || !$product->store) {
                Log::warning('Product visit not tracked - product or store not found', [
                    'product_id' => $productId
                ]);
                return;
            }

            StoreVisitor::create([
                'store_id' => $product->store_id,
                'user_id' => $user->id,
                'product_id' => $productId,
                'visit_type' => 'product',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::debug('Product visit tracked successfully', [
                'product_id' => $productId,
                'store_id' => $product->store_id,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            // Log error but don't disrupt the main flow
            Log::error('Failed to track product visit: ' . $e->getMessage(), [
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

