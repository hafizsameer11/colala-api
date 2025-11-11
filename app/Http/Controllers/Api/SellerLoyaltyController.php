<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellerLoyaltyController extends Controller
{
    public function getCustomerPoints(Request $request)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }
            $totalPointsAwarded = LoyaltyPoint::where('store_id', $store->id)
                ->where('source', 'order')
                ->sum('points');
            $customers = LoyaltyPoint::select([
                    'user_id',
                    DB::raw('SUM(points) as total_points')
                ])
                ->where('store_id', $store->id)
                ->where('source', 'order')
                ->with(['user:id,full_name,profile_picture'])
                ->groupBy('user_id')
                ->orderByDesc('total_points')
                ->get()
                ->map(function ($loyaltyPoint) {
                    return [
                        'user_id' => $loyaltyPoint->user_id,
                        'name' => $loyaltyPoint->user->full_name,
                        'profile_picture' => $loyaltyPoint->user->profile_picture,
                        'points' => (int)$loyaltyPoint->total_points,
                    ];
                });

            return ResponseHelper::success([
                'total_points_balance' => (int)$totalPointsAwarded,
                'customers' => $customers,
            ], 'Customer loyalty points retrieved successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getLoyaltySettings(Request $request)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Get loyalty settings for this store
            $settings = \App\Models\LoyaltySetting::where('store_id', $store->id)->first();
            
            if (!$settings) {
                // Return default settings if none exist
                $settings = (object)[
                    'points_per_order' => 0,
                    'points_per_referral' => 0,
                    'enable_order_points' => false,
                    'enable_referral_points' => false,
                ];
            }

            return ResponseHelper::success($settings, 'Loyalty settings retrieved successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function updateLoyaltySettings(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'points_per_order' => 'required|integer|min:0',
                'points_per_referral' => 'required|integer|min:0',
                'enable_order_points' => 'required|boolean',
                'enable_referral_points' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Update or create loyalty settings
            $settings = \App\Models\LoyaltySetting::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'points_per_order' => $request->points_per_order,
                    'points_per_referral' => $request->points_per_referral,
                    'enable_order_points' => $request->enable_order_points,
                    'enable_referral_points' => $request->enable_referral_points,
                ]
            );

            return ResponseHelper::success($settings, 'Loyalty settings updated successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
