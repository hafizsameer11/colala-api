<?php 

namespace App\Http\Controllers\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class LoyaltyController extends Controller
{
    public function myPoints(Request $request)
    {
        try {
            $user = $request->user();

            $total = $user->wallet->loyality_points ?? 0;

            // Group points per store with store info
            $perStore = LoyaltyPoint::query()
                ->select('stores.id as store_id', 'stores.store_name', 'stores.profile_image', DB::raw('SUM(loyalty_points.points) as total_points'))
                ->join('stores', 'stores.id', '=', 'loyalty_points.store_id')
                ->where('loyalty_points.user_id', $user->id)
                ->groupBy('stores.id', 'stores.store_name', 'stores.profile_image')
                ->orderByDesc(DB::raw('SUM(loyalty_points.points)'))
                ->get();

            return ResponseHelper::success([
                'total_points' => $total,
                'stores'       => $perStore,
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
