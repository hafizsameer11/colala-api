<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerLeaderboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $now = Carbon::now();
            $windows = [
                'today'   => [Carbon::today(), Carbon::today()->endOfDay()],
                'weekly'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'all'     => [null, null],
            ];

            // Fetch aggregates per window
            $results = [];
            $allStoreIds = collect();
            foreach ($windows as $key => [$start, $end]) {
                $q = LoyaltyPoint::select('store_id', DB::raw('SUM(points) as total_points'))
                    ->where('source', 'order');
                if ($start && $end) {
                    $q->whereBetween('created_at', [$start, $end]);
                }
                $rows = $q->groupBy('store_id')
                    ->orderByDesc('total_points')
                    ->limit(100)
                    ->get();
                $results[$key] = $rows;
                $allStoreIds = $allStoreIds->merge($rows->pluck('store_id'));
            }

            $storeIds = $allStoreIds->unique()->values();
            $stores = Store::whereIn('id', $storeIds)
                ->withCount('followers')
                ->get()
                ->keyBy('id');

            $build = function ($rows) use ($stores) {
                return $rows->map(function ($r) use ($stores) {
                    $store = $stores->get($r->store_id);
                    return [
                        'store_id' => $r->store_id,
                        'store_name' => $store?->store_name,
                        'total_points' => (int) $r->total_points,
                        'followers_count' => (int) ($store?->followers_count ?? 0),
                        'average_rating' => $store?->average_rating,
                        'profile_image' => $store?->profile_image,
                    ];
                })->values();
            };

            return ResponseHelper::success([
                'today'   => $build($results['today']),
                'weekly'  => $build($results['weekly']),
                'monthly' => $build($results['monthly']),
                'all'     => $build($results['all']),
            ]);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}


