<?php 

namespace App\Http\Controllers\Api\Seller;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{LoyaltySetting, LoyaltyPoint};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class LoyaltyController extends Controller
{
    // View or update store's loyalty point settings
    public function settings(Request $request)
    {
        try {
            $store = $request->user()->store;
            $setting = LoyaltySetting::firstOrCreate(['store_id'=>$store->id]);

            if ($request->isMethod('post')) {
                $setting->update($request->only([
                    'points_per_order','points_per_referral',
                    'enable_order_points','enable_referral_points'
                ]));
            }

            return ResponseHelper::success($setting);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // Show customers and their total points for this store
    public function customers(Request $request)
    {
        try {
            $store = $request->user()->store;

            $customers = LoyaltyPoint::with('user:id,name,profile_picture')
                ->selectRaw('user_id, SUM(points) as total_points')
                ->where('store_id', $store->id)
                ->groupBy('user_id')
                ->get();

            return ResponseHelper::success($customers);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
