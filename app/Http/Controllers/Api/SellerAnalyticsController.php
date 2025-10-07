<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\StoreReview;
use App\Models\ProductStat;
use App\Models\ServiceStat;
use App\Models\LoyaltyPoint;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            $period = $request->query('period', '30'); // days
            $startDate = Carbon::now()->subDays($period);
            $endDate = Carbon::now();

            // Summary chart data (daily breakdown)
            $chartData = $this->getChartData($store->id, $startDate, $endDate);

            // Detailed analytics
            $analytics = [
                'sales_orders' => $this->getSalesOrdersMetrics($store->id, $startDate, $endDate),
                'customer_insights' => $this->getCustomerInsights($store->id, $startDate, $endDate),
                'product_performance' => $this->getProductPerformance($store->id, $startDate, $endDate),
                'financial_metrics' => $this->getFinancialMetrics($store->id, $startDate, $endDate),
            ];

            return ResponseHelper::success([
                'period' => $period,
                'chart_data' => $chartData,
                'analytics' => $analytics,
            ]);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    private function getChartData($storeId, $startDate, $endDate)
    {
        $data = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dayStart = $current->copy()->startOfDay();
            $dayEnd = $current->copy()->endOfDay();

            // Get daily metrics
            $impressions = ProductStat::whereHas('product', function($query) use ($storeId) {
                    $query->where('store_id', $storeId);
                })
                ->where('event_type', 'impression')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $visitors = ProductStat::whereHas('product', function($query) use ($storeId) {
                    $query->where('store_id', $storeId);
                })
                ->where('event_type', 'view')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->distinct('user_id')
                ->count('user_id');

            $orders = StoreOrder::where('store_id', $storeId)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $data[] = [
                'date' => $current->format('Y-m-d'),
                'impressions' => (int)$impressions,
                'visitors' => (int)$visitors,
                'orders' => (int)$orders,
            ];

            $current->addDay();
        }

        return $data;
    }

    private function getSalesOrdersMetrics($storeId, $startDate, $endDate)
    {
        $totalSales = StoreOrder::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->sum('subtotal_with_shipping') ?? 0;

        $totalOrders = StoreOrder::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $deliveredOrders = StoreOrder::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->count();

        $fulfillmentRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0;

        $refundedOrders = StoreOrder::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'refunded')
            ->count();

        // Repeat purchase rate (customers with >1 order)
        $repeatCustomers = DB::table('store_orders')
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $totalCustomers = DB::table('store_orders')
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('order_id')
            ->count();

        $repeatPurchaseRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0;

        return [
            'total_sales' => (int)$totalSales,
            'no_of_orders' => (int)$totalOrders,
            'fulfillment_rate' => $fulfillmentRate,
            'refunded_orders' => (int)$refundedOrders,
            'repeat_purchase_rate' => $repeatPurchaseRate,
        ];
    }

    private function getCustomerInsights($storeId, $startDate, $endDate)
    {
        // New customers (first-time buyers)
        $newCustomers = DB::table('store_orders as so1')
            ->join('orders as o1', 'so1.order_id', '=', 'o1.id')
            ->where('so1.store_id', $storeId)
            ->whereBetween('so1.created_at', [$startDate, $endDate])
            ->whereNotExists(function ($query) use ($storeId, $startDate) {
                $query->select(DB::raw(1))
                    ->from('store_orders as so2')
                    ->join('orders as o2', 'so2.order_id', '=', 'o2.id')
                    ->where('so2.store_id', $storeId)
                    ->where('so2.created_at', '<', $startDate)
                    ->whereColumn('o1.user_id', 'o2.user_id');
            })
            ->distinct('o1.user_id')
            ->count();

        // Returning customers
        $returningCustomers = DB::table('store_orders as so1')
            ->join('orders as o1', 'so1.order_id', '=', 'o1.id')
            ->where('so1.store_id', $storeId)
            ->whereBetween('so1.created_at', [$startDate, $endDate])
            ->whereExists(function ($query) use ($storeId, $startDate) {
                $query->select(DB::raw(1))
                    ->from('store_orders as so2')
                    ->join('orders as o2', 'so2.order_id', '=', 'o2.id')
                    ->where('so2.store_id', $storeId)
                    ->where('so2.created_at', '<', $startDate)
                    ->whereColumn('o1.user_id', 'o2.user_id');
            })
            ->distinct('o1.user_id')
            ->count();

        $totalCustomers = $newCustomers + $returningCustomers;
        $returningRate = $totalCustomers > 0 ? round(($returningCustomers / $totalCustomers) * 100, 1) : 0;

        // Reviews
        $customerReviews = ProductReview::whereHas('orderItem.product', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })->whereBetween('created_at', [$startDate, $endDate])->count();

        $productReviews = ProductReview::whereHas('orderItem.product', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })->whereBetween('created_at', [$startDate, $endDate])->count();

        $storeReviews = StoreReview::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Average ratings
        $avgProductRating = ProductReview::whereHas('orderItem.product', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })->whereBetween('created_at', [$startDate, $endDate])->avg('rating') ?? 0;

        $avgStoreRating = StoreReview::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('rating') ?? 0;

        return [
            'new_customers' => (int)$newCustomers,
            'returning_customers' => $returningRate,
            'customer_reviews' => $returningRate, // Using returning rate as placeholder
            'product_reviews' => (int)$productReviews,
            'store_reviews' => $returningRate, // Using returning rate as placeholder
            'av_product_rating' => round($avgProductRating, 1),
            'av_store_rating' => round($avgStoreRating, 1),
        ];
    }

    private function getProductPerformance($storeId, $startDate, $endDate)
    {
        $totalImpressions = ProductStat::whereHas('product', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->where('event_type', 'impression')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalClicks = ProductStat::whereHas('product', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->where('event_type', 'click')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $ordersPlaced = StoreOrder::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $clickRate = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 1) : 0;
        $conversionRate = $totalClicks > 0 ? round(($ordersPlaced / $totalClicks) * 100, 1) : 0;

        return [
            'total_impression' => (int)$totalImpressions,
            'total_clicks' => $clickRate,
            'orders_placed' => $conversionRate,
        ];
    }

    private function getFinancialMetrics($storeId, $startDate, $endDate)
    {
        $totalRevenue = StoreOrder::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->sum('subtotal_with_shipping') ?? 0;

        $refundedAmount = StoreOrder::where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'refunded')
            ->sum('subtotal_with_shipping') ?? 0;

        $netRevenue = $totalRevenue - $refundedAmount;
        $profitMargin = $totalRevenue > 0 ? round(($netRevenue / $totalRevenue) * 100, 1) : 0;

        return [
            'total_revenue' => (int)$totalRevenue,
            'loss_from' => (int)$refundedAmount,
            'profit_margin' => $profitMargin,
        ];
    }
}
