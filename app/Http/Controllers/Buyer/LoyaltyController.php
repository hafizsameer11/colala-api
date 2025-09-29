<?php 

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class LoyaltyController extends Controller
{
    public function myPoints(Request $request)
    {
        try {
            $user = $request->user();

            $total = $user->wallet->loyality_points ?? 0;

            // Group points per store
            $perStore = LoyaltyPoint::with('store:id,store_name,profile_image')
                ->selectRaw('store_id, SUM(points) as total_points')
                ->where('user_id', $user->id)
                ->groupBy('store_id')
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
