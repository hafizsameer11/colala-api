<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Chat;
use App\Models\Post;
use App\Models\Transaction;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get comprehensive analytics dashboard data
     */
    public function getAnalyticsDashboard(Request $request)
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

            // Site Statistics
            $siteStats = [
                'total_users' => User::count(),
                'total_sellers' => User::where('role', 'seller')->count(),
                'total_buyers' => User::where('role', 'buyer')->count(),
                'total_orders' => Order::count(),
                'active_orders' => \App\Models\StoreOrder::where('status', 'active')->count(),
                'completed_orders' => \App\Models\StoreOrder::where('status', 'completed')->count(),
                'total_products' => Product::count(),
                'total_chats' => Chat::count(),
                'total_posts' => Post::count(),
                'total_revenue' => Transaction::where('status', 'successful')->sum('amount'),
            ];

            // User registration trends
            $userTrends = User::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_users,
                SUM(CASE WHEN role = "buyer" THEN 1 ELSE 0 END) as buyers,
                SUM(CASE WHEN role = "seller" THEN 1 ELSE 0 END) as sellers
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Order trends
            $orderTrends = \App\Models\StoreOrder::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_orders,
                SUM(subtotal_with_shipping) as total_revenue,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_orders
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Revenue trends
            $revenueTrends = Transaction::selectRaw('
                DATE(created_at) as date,
                SUM(amount) as total_revenue,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = "successful" THEN amount ELSE 0 END) as successful_revenue
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Top performing stores
            $topStores = Store::withCount(['orders', 'products'])
                ->withSum('orders', 'subtotal_with_shipping')
                ->orderByDesc('orders_sum_subtotal_with_shipping')
                ->limit(10)
                ->get();

            // Category breakdown
            $categoryStats = Product::selectRaw('
                categories.title as category_name,
                COUNT(*) as product_count,
                AVG(products.price) as avg_price
            ')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.title')
            ->get();

            // Chat analytics
            $chatAnalytics = [
                'total_chats' => Chat::count(),
                'unread_chats' => Chat::whereHas('messages', function($q) {
                    $q->where('is_read', false);
                })->count(),
                'dispute_chats' => Chat::where('type', 'dispute')->count(),
                'support_chats' => Chat::where('type', 'support')->count(),
                'general_chats' => Chat::where('type', 'general')->count(),
            ];

            // Social media analytics
            $socialAnalytics = [
                'total_posts' => Post::count(),
                'total_likes' => DB::table('post_likes')->count(),
                'total_comments' => DB::table('post_comments')->count(),
                'total_shares' => DB::table('post_shares')->count(),
            ];

            return ResponseHelper::success([
                'site_statistics' => $siteStats,
                'user_trends' => $userTrends,
                'order_trends' => $orderTrends,
                'revenue_trends' => $revenueTrends,
                'top_stores' => $topStores->map(function ($store) {
                    return [
                        'id' => $store->id,
                        'store_name' => $store->store_name,
                        'total_orders' => $store->orders_count,
                        'total_products' => $store->products_count,
                        'total_revenue' => $store->orders_sum_subtotal_with_shipping ?? 0,
                    ];
                }),
                'category_breakdown' => $categoryStats,
                'chat_analytics' => $chatAnalytics,
                'social_analytics' => $socialAnalytics,
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
     * Get user analytics
     */
    public function getUserAnalytics(Request $request)
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

            // User registration trends
            $registrationTrends = User::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_users,
                SUM(CASE WHEN role = "buyer" THEN 1 ELSE 0 END) as buyers,
                SUM(CASE WHEN role = "seller" THEN 1 ELSE 0 END) as sellers
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // User activity statistics
            $activityStats = [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'inactive_users' => User::where('status', 'inactive')->count(),
                'buyer_users' => User::where('role', 'buyer')->count(),
                'seller_users' => User::where('role', 'seller')->count(),
                'users_with_orders' => User::whereHas('orders')->count(),
                'users_with_transactions' => User::whereHas('transactions')->count(),
            ];

            // Top active users
            $topActiveUsers = User::withCount(['orders', 'transactions'])
                ->orderByDesc('orders_count')
                ->limit(10)
                ->get();

            return ResponseHelper::success([
                'registration_trends' => $registrationTrends,
                'activity_stats' => $activityStats,
                'top_active_users' => $topActiveUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'orders_count' => $user->orders_count,
                        'transactions_count' => $user->transactions_count,
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
     * Get revenue analytics
     */
    public function getRevenueAnalytics(Request $request)
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

            // Revenue trends
            $revenueTrends = Transaction::selectRaw('
                DATE(created_at) as date,
                SUM(amount) as total_revenue,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = "successful" THEN amount ELSE 0 END) as successful_revenue,
                SUM(CASE WHEN status = "failed" THEN amount ELSE 0 END) as failed_revenue
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Revenue by transaction type
            $revenueByType = Transaction::selectRaw('
                type,
                SUM(amount) as total_revenue,
                COUNT(*) as total_transactions,
                AVG(amount) as avg_amount
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('type')
            ->get();

            // Revenue statistics
            $revenueStats = [
                'total_revenue' => Transaction::where('status', 'successful')->sum('amount'),
                'total_transactions' => Transaction::count(),
                'successful_transactions' => Transaction::where('status', 'successful')->count(),
                'failed_transactions' => Transaction::where('status', 'failed')->count(),
                'average_transaction_amount' => Transaction::where('status', 'successful')->avg('amount'),
            ];

            return ResponseHelper::success([
                'revenue_trends' => $revenueTrends,
                'revenue_by_type' => $revenueByType,
                'revenue_stats' => $revenueStats,
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
     * Get product analytics
     */
    public function getProductAnalytics(Request $request)
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

            // Product trends
            $productTrends = Product::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_products,
                AVG(price) as avg_price,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_products
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Top performing products
            $topProducts = Product::withCount(['orderItems'])
                ->withSum('orderItems', 'line_total')
                ->orderByDesc('order_items_sum_line_total')
                ->limit(10)
                ->get();

            // Category breakdown
            $categoryStats = Product::selectRaw('
                categories.title as category_name,
                COUNT(*) as product_count,
                AVG(products.price) as avg_price,
                SUM(CASE WHEN products.status = "active" THEN 1 ELSE 0 END) as active_products
            ')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.title')
            ->get();

            return ResponseHelper::success([
                'product_trends' => $productTrends,
                'top_products' => $topProducts->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'orders_count' => $product->order_items_count,
                        'total_revenue' => $product->order_items_sum_line_total ?? 0,
                    ];
                }),
                'category_breakdown' => $categoryStats,
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
