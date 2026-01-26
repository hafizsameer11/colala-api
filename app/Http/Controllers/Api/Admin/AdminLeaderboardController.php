<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Traits\PeriodFilterTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLeaderboardController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get comprehensive leaderboard data
     */
    public function getLeaderboard(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            // If period is provided, use it; otherwise use default windows
            if ($period) {
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $windows = [
                        $period => [$dateRange['start'], $dateRange['end']]
                    ];
                } else {
                    $windows = ['all_time' => [null, null]];
                }
            } else {
                $now = Carbon::now();
                $windows = [
                    'today'   => [Carbon::today(), Carbon::today()->endOfDay()],
                    'weekly'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                    'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                    'all'     => [null, null],
                ];
            }

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
                ->withCount(['followers', 'orders', 'products'])
                ->withSum('orders', 'subtotal_with_shipping')
                ->get()
                ->keyBy('id');

            // If no stores have points, include all stores with 0 points
            if ($storeIds->isEmpty()) {
                $allStores = Store::withCount(['followers', 'orders', 'products'])
                    ->withSum('orders', 'subtotal_with_shipping')
                    ->get();
                $stores = $allStores->keyBy('id');
                // Create empty results for all periods
                foreach ($windows as $key => $_) {
                    $results[$key] = collect();
                }
            }

            $build = function ($rows) use ($stores, $storeIds) {
                return $rows->map(function ($r) use ($stores) {
                    $store = $stores->get($r->store_id);
                    return [
                        'store_id' => $r->store_id,
                        'store_name' => $store?->store_name,
                        'seller_name' => $store?->user?->full_name,
                        'total_points' => (int) $r->total_points,
                        'followers_count' => (int) ($store?->followers_count ?? 0),
                        'orders_count' => (int) ($store?->orders_count ?? 0),
                        'products_count' => (int) ($store?->products_count ?? 0),
                        'total_revenue' => (float) ($store?->orders_sum_subtotal_with_shipping ?? 0),
                        'average_rating' => $store?->average_rating,
                        'profile_image' => $store?->profile_image,
                        'store_location' => $store?->store_location,
                        'store_status' => $store?->status,
                    ];
                })->values();
            };

            // If no stores have points, show all stores with 0 points
            if ($storeIds->isEmpty()) {
                $allStores = Store::withCount(['followers', 'orders', 'products'])
                    ->withSum('orders', 'subtotal_with_shipping')
                    ->get();
                $build = function ($_) use ($allStores) {
                    return $allStores->map(function ($store) {
                        return [
                            'store_id' => $store->id,
                            'store_name' => $store->store_name,
                            'seller_name' => $store->user?->full_name,
                            'total_points' => 0,
                            'followers_count' => (int) ($store->followers_count ?? 0),
                            'orders_count' => (int) ($store->orders_count ?? 0),
                            'products_count' => (int) ($store->products_count ?? 0),
                            'total_revenue' => (float) ($store->orders_sum_subtotal_with_shipping ?? 0),
                            'average_rating' => $store->average_rating,
                            'profile_image' => $store->profile_image,
                            'store_location' => $store->store_location,
                            'store_status' => $store->status,
                        ];
                    })->values();
                };
            }

            // Return results based on period or all windows
            if ($period) {
                $key = $period === 'all_time' ? 'all' : $period;
                return ResponseHelper::success([
                    $key => $build($results[$key] ?? collect()),
                ]);
            } else {
                return ResponseHelper::success([
                    'today'   => $build($results['today'] ?? collect()),
                    'weekly'  => $build($results['weekly'] ?? collect()),
                    'monthly' => $build($results['monthly'] ?? collect()),
                    'all'     => $build($results['all'] ?? collect()),
                ]);
            }
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get top performing stores by revenue
     */
    public function getTopStoresByRevenue(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            $dateRange = $this->getDateRange($period);
            
            // Use period if provided, otherwise fall back to date_from/date_to
            if ($dateRange) {
                $dateFrom = $dateRange['start']->format('Y-m-d');
                $dateTo = $dateRange['end']->format('Y-m-d');
            } else {
                $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
                $dateTo = $request->get('date_to', now()->format('Y-m-d'));
            }

            $topStores = Store::withCount(['orders', 'products'])
                ->withSum('orders', 'subtotal_with_shipping')
                ->whereHas('orders', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('created_at', [$dateFrom, $dateTo]);
                })
                ->orderByDesc('orders_sum_subtotal_with_shipping')
                ->limit(20)
                ->get();

            return ResponseHelper::success([
                'top_stores' => $topStores->map(function ($store) {
                    return [
                        'store_id' => $store->id,
                        'store_name' => $store->store_name,
                        'seller_name' => $store->user?->full_name,
                        'total_revenue' => (float) ($store->orders_sum_subtotal_with_shipping ?? 0),
                        'orders_count' => $store->orders_count,
                        'products_count' => $store->products_count,
                        'average_rating' => $store->average_rating,
                        'profile_image' => $store->profile_image,
                        'store_location' => $store->store_location,
                    ];
                }),
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get top performing stores by orders
     */
    public function getTopStoresByOrders(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            $dateRange = $this->getDateRange($period);
            
            // Use period if provided, otherwise fall back to date_from/date_to
            if ($dateRange) {
                $dateFrom = $dateRange['start']->format('Y-m-d');
                $dateTo = $dateRange['end']->format('Y-m-d');
            } else {
                $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
                $dateTo = $request->get('date_to', now()->format('Y-m-d'));
            }

            $topStores = Store::withCount(['orders'])
                ->withSum('orders', 'subtotal_with_shipping')
                ->whereHas('orders', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('created_at', [$dateFrom, $dateTo]);
                })
                ->orderByDesc('orders_count')
                ->limit(20)
                ->get();

            return ResponseHelper::success([
                'top_stores' => $topStores->map(function ($store) {
                    return [
                        'store_id' => $store->id,
                        'store_name' => $store->store_name,
                        'seller_name' => $store->user?->full_name,
                        'orders_count' => $store->orders_count,
                        'total_revenue' => (float) ($store->orders_sum_subtotal_with_shipping ?? 0),
                        'average_rating' => $store->average_rating,
                        'profile_image' => $store->profile_image,
                        'store_location' => $store->store_location,
                    ];
                }),
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get top performing stores by followers
     */
    public function getTopStoresByFollowers(Request $request)
    {
        try {
            $topStores = Store::withCount(['followers', 'orders', 'products'])
                ->withSum('orders', 'subtotal_with_shipping')
                ->orderByDesc('followers_count')
                ->limit(20)
                ->get();

            return ResponseHelper::success([
                'top_stores' => $topStores->map(function ($store) {
                    return [
                        'store_id' => $store->id,
                        'store_name' => $store->store_name,
                        'seller_name' => $store->user?->full_name,
                        'followers_count' => $store->followers_count,
                        'orders_count' => $store->orders_count,
                        'products_count' => $store->products_count,
                        'total_revenue' => (float) ($store->orders_sum_subtotal_with_shipping ?? 0),
                        'average_rating' => $store->average_rating,
                        'profile_image' => $store->profile_image,
                        'store_location' => $store->store_location,
                    ];
                })
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get leaderboard analytics
     */
    public function getLeaderboardAnalytics(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            $dateRange = $this->getDateRange($period);
            
            // Use period if provided, otherwise fall back to date_from/date_to
            if ($dateRange) {
                $dateFrom = $dateRange['start']->format('Y-m-d');
                $dateTo = $dateRange['end']->format('Y-m-d');
            } else {
                $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
                $dateTo = $request->get('date_to', now()->format('Y-m-d'));
            }

            // Store performance trends
            $storeTrends = Store::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as new_stores,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_stores
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Revenue trends
            $revenueTrends = Order::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_orders,
                SUM(grand_total) as total_revenue,
                AVG(grand_total) as avg_order_value
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Top categories
            $topCategories = Product::selectRaw('
                categories.name as category_name,
                COUNT(*) as product_count,
                AVG(products.price) as avg_price
            ')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('products.created_at', [$dateFrom, $dateTo])
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('product_count')
            ->limit(10)
            ->get();

            return ResponseHelper::success([
                'store_trends' => $storeTrends,
                'revenue_trends' => $revenueTrends,
                'top_categories' => $topCategories,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
