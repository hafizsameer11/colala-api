<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltySetting;
use App\Models\Store;
use App\Models\User;
use App\Services\CouponService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerCouponLoyaltyController extends Controller
{
    private CouponService $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * Get all coupons for a specific seller
     */
    public function getSellerCoupons(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $coupons = Coupon::where('store_id', $store->id)
                ->with('store')
                ->latest()
                ->paginate($request->get('per_page', 20));

            return ResponseHelper::success([
                'coupons' => CouponResource::collection($coupons),
                'pagination' => [
                    'current_page' => $coupons->currentPage(),
                    'last_page' => $coupons->lastPage(),
                    'per_page' => $coupons->perPage(),
                    'total' => $coupons->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get coupon details
     */
    public function getCouponDetails($userId, $couponId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $coupon = Coupon::where('id', $couponId)
                ->where('store_id', $store->id)
                ->with('store')
                ->firstOrFail();

            return ResponseHelper::success([
                'coupon' => new CouponResource($coupon),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                    'user' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create coupon for a seller
     */
    public function createCoupon(CouponRequest $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $coupon = $this->couponService->create($store, $request->validated());

            return ResponseHelper::success([
                'coupon' => new CouponResource($coupon),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Coupon created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update coupon
     */
    public function updateCoupon(CouponRequest $request, $userId, $couponId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $coupon = Coupon::where('id', $couponId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $coupon = $this->couponService->update($coupon, $request->validated());

            return ResponseHelper::success([
                'coupon' => new CouponResource($coupon),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Coupon updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete coupon
     */
    public function deleteCoupon($userId, $couponId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $coupon = Coupon::where('id', $couponId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $this->couponService->delete($coupon);

            return ResponseHelper::success(null, 'Coupon deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get loyalty settings for a seller
     */
    public function getLoyaltySettings($userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $settings = LoyaltySetting::where('store_id', $store->id)->first();
            
            if (!$settings) {
                $settings = (object)[
                    'points_per_order' => 0,
                    'points_per_referral' => 0,
                    'enable_order_points' => false,
                    'enable_referral_points' => false,
                ];
            }

            return ResponseHelper::success([
                'settings' => $settings,
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                    'user' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update loyalty settings for a seller
     */
    public function updateLoyaltySettings(Request $request, $userId)
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

            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $settings = LoyaltySetting::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'points_per_order' => $request->points_per_order,
                    'points_per_referral' => $request->points_per_referral,
                    'enable_order_points' => $request->enable_order_points,
                    'enable_referral_points' => $request->enable_referral_points,
                ]
            );

            return ResponseHelper::success([
                'settings' => $settings,
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Loyalty settings updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get customer loyalty points for a seller
     */
    public function getCustomerPoints(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            // Get total points awarded by this store
            $totalPointsAwarded = LoyaltyPoint::where('store_id', $store->id)
                ->where('source', 'order')
                ->sum('points');

            // Get customers with their total points for this store
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
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Customer loyalty points retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get statistics for seller coupons and loyalty points
     */
    public function getStatistics($userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            // Coupon statistics
            $couponStats = Coupon::where('store_id', $store->id)
                ->selectRaw('
                    COUNT(*) as total_coupons,
                    SUM(times_used) as total_usage,
                    AVG(times_used) as avg_usage,
                    COUNT(CASE WHEN status = "active" THEN 1 END) as active_coupons
                ')
                ->first();

            // Loyalty points statistics
            $loyaltyStats = LoyaltyPoint::where('store_id', $store->id)
                ->selectRaw('
                    COUNT(*) as total_transactions,
                    SUM(points) as total_points_awarded,
                    AVG(points) as avg_points_per_transaction,
                    COUNT(DISTINCT user_id) as unique_customers
                ')
                ->first();

            return ResponseHelper::success([
                'coupons' => [
                    'total' => $couponStats->total_coupons ?? 0,
                    'active' => $couponStats->active_coupons ?? 0,
                    'total_usage' => $couponStats->total_usage ?? 0,
                    'avg_usage' => round($couponStats->avg_usage ?? 0, 2),
                ],
                'loyalty_points' => [
                    'total_transactions' => $loyaltyStats->total_transactions ?? 0,
                    'total_points_awarded' => $loyaltyStats->total_points_awarded ?? 0,
                    'avg_points_per_transaction' => round($loyaltyStats->avg_points_per_transaction ?? 0, 2),
                    'unique_customers' => $loyaltyStats->unique_customers ?? 0,
                ],
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on coupons
     */
    public function bulkActionCoupons(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $request->validate([
                'action' => 'required|in:delete,activate,deactivate',
                'coupon_ids' => 'required|array|min:1',
                'coupon_ids.*' => 'integer|exists:coupons,id'
            ]);

            $couponIds = $request->coupon_ids;
            $action = $request->action;

            // Verify all coupons belong to this store
            $coupons = Coupon::whereIn('id', $couponIds)
                ->where('store_id', $store->id)
                ->get();

            if ($coupons->count() !== count($couponIds)) {
                return ResponseHelper::error('Some coupons do not belong to this seller', 403);
            }

            switch ($action) {
                case 'delete':
                    Coupon::whereIn('id', $couponIds)->delete();
                    return ResponseHelper::success(null, 'Coupons deleted successfully');
                case 'activate':
                    Coupon::whereIn('id', $couponIds)->update(['status' => 'active']);
                    return ResponseHelper::success(null, 'Coupons activated successfully');
                case 'deactivate':
                    Coupon::whereIn('id', $couponIds)->update(['status' => 'inactive']);
                    return ResponseHelper::success(null, 'Coupons deactivated successfully');
            }

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get coupon usage analytics
     */
    public function getCouponAnalytics(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Coupon usage over time
            $usageOverTime = Coupon::where('store_id', $store->id)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(created_at) as date, SUM(times_used) as usage')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top performing coupons
            $topCoupons = Coupon::where('store_id', $store->id)
                ->orderByDesc('times_used')
                ->limit(5)
                ->get(['code', 'times_used', 'discount_value', 'discount_type']);

            return ResponseHelper::success([
                'usage_over_time' => $usageOverTime,
                'top_coupons' => $topCoupons,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
