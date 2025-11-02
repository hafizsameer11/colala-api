<?php

namespace App\Http\Controllers\Api\Seller;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SellerStoreSettingsController extends Controller
{
    /**
     * Get store phone visibility setting
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPhoneVisibility()
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            return ResponseHelper::success([
                'is_phone_visible' => (bool) $store->is_phone_visible,
                'store_phone' => $store->store_phone,
            ], 'Phone visibility setting retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to get phone visibility setting: ' . $e->getMessage());
            return ResponseHelper::error('Failed to retrieve phone visibility setting', 500);
        }
    }
    public function getPhoneVisibilityBySellerId($sellerId)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $sellerId)->first();

            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            return ResponseHelper::success([
                'is_phone_visible' => (bool) $store->is_phone_visible,
                'store_phone' => $store->store_phone,
            ], 'Phone visibility setting retrieved successfully by seller id');

        } catch (\Exception $e) {
            Log::error('Failed to get phone visibility setting by seller id: ' . $e->getMessage());
            return ResponseHelper::error('Failed to retrieve phone visibility setting', 500);
        }
    }

    /**
     * Update store phone visibility setting
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePhoneVisibility(Request $request)
    {
        try {
            $request->validate([
                'is_phone_visible' => 'required|boolean',
            ]);

            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            $store->update([
                'is_phone_visible' => $request->is_phone_visible,
            ]);

            $message = $request->is_phone_visible 
                ? 'Phone number is now visible to customers' 
                : 'Phone number is now hidden from customers';

            return ResponseHelper::success([
                'is_phone_visible' => (bool) $store->is_phone_visible,
                'store_phone' => $store->store_phone,
            ], $message);

        } catch (\Exception $e) {
            Log::error('Failed to update phone visibility: ' . $e->getMessage());
            return ResponseHelper::error('Failed to update phone visibility setting', 500);
        }
    }
}
