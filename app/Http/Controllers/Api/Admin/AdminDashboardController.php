<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Chat;
use App\Models\Transaction;
use App\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    /**
     * Get complete dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $period = $request->get('period', 'all_time');
            
            $data = [
                'buyer_stats' => $this->getBuyerStats($period),
                'seller_stats' => $this->getSellerStats($period),
                'site_stats' => $this->getSiteStats($period),
                'latest_chats' => $this->getLatestChats(),
                'latest_orders' => $this->getLatestOrders()
            ];

            return ResponseHelper::success($data, 'Dashboard data retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get buyer statistics
     */
    public function buyerStats(Request $request)
    {
        try {
            $period = $request->get('period', 'all_time');
            $stats = $this->getBuyerStats($period);
            return ResponseHelper::success($stats, 'Buyer statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller statistics
     */
    public function sellerStats(Request $request)
    {
        try {
            $period = $request->get('period', 'all_time');
            $stats = $this->getSellerStats($period);
            return ResponseHelper::success($stats, 'Seller statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get site statistics (chart data)
     */
    public function siteStats(Request $request)
    {
        try {
            $period = $request->get('period', 'all_time');
            $stats = $this->getSiteStats($period);
            return ResponseHelper::success($stats, 'Site statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get latest chats
     */
    public function latestChats()
    {
        try {
            $chats = $this->getLatestChats();
            return ResponseHelper::success($chats, 'Latest chats retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get latest orders
     */
    public function latestOrders(Request $request)
    {
        try {
            $orders = $this->getLatestOrders($request);
            return ResponseHelper::success($orders, 'Latest orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Filter orders by status
     */
    public function filterOrders(Request $request)
    {
        try {
            $status = $request->get('status', 'all');
            $search = $request->get('search', '');
            
            $orders = $this->getFilteredOrders($status, $search);
            return ResponseHelper::success($orders, 'Filtered orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on orders
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'order_ids' => 'required|array',
                'action' => 'required|string|in:update_status,delete'
            ]);

            $orderIds = $request->order_ids;
            $action = $request->action;

            if ($action === 'update_status') {
                $request->validate(['new_status' => 'required|string']);
                $newStatus = $request->new_status;
                
                StoreOrder::whereIn('id', $orderIds)->update(['status' => $newStatus]);
                $message = "Orders status updated to {$newStatus}";
            } else {
                StoreOrder::whereIn('id', $orderIds)->delete();
                $message = "Orders deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get date range based on period
     */
    private function getDateRange($period)
    {
        switch ($period) {
            case 'today':
                return [
                    'start' => now()->startOfDay(),
                    'end' => now()->endOfDay(),
                    'previous_start' => now()->subDay()->startOfDay(),
                    'previous_end' => now()->subDay()->endOfDay()
                ];
            case 'this_week':
                return [
                    'start' => now()->startOfWeek(),
                    'end' => now()->endOfWeek(),
                    'previous_start' => now()->subWeek()->startOfWeek(),
                    'previous_end' => now()->subWeek()->endOfWeek()
                ];
            case 'this_month':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                    'previous_start' => now()->subMonth()->startOfMonth(),
                    'previous_end' => now()->subMonth()->endOfMonth()
                ];
            case 'this_year':
                return [
                    'start' => now()->startOfYear(),
                    'end' => now()->endOfYear(),
                    'previous_start' => now()->subYear()->startOfYear(),
                    'previous_end' => now()->subYear()->endOfYear()
                ];
            default:
                return null; // All time
        }
    }

    /**
     * Calculate percentage increase
     */
    private function calculateIncrease($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get buyer statistics data
     */
    private function getBuyerStats($period = 'all_time')
    {
        $dateRange = $this->getDateRange($period);
        
        // Build queries with period filter
        $userQuery = User::where('role', 'buyer');
        $orderQuery = Order::query();
        // Fixed: Use StoreOrder with status='completed' or 'delivered' (both are considered completed)
        $completedOrderQuery = StoreOrder::whereIn('status', ['completed', 'delivered'])
            ->whereHas('order.user', function ($q) {
                $q->where('role', 'buyer');
            });
        $transactionQuery = Transaction::query();

        if ($dateRange) {
            $tableName = (new StoreOrder())->getTable();
            $userQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            $orderQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            // Apply date filter to StoreOrder created_at
            $completedOrderQuery->whereBetween($tableName . '.created_at', [$dateRange['start'], $dateRange['end']]);
            $transactionQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $totalUsers = $userQuery->count();
        $totalOrders = $orderQuery->count();
        $completedOrders = $completedOrderQuery->count();
        $totalTransactions = 0;
        try {
            $totalTransactions = $transactionQuery->count();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Calculate previous period values for increase calculation
        $previousUsers = 0;
        $previousOrders = 0;
        $previousCompletedOrders = 0;
        $previousTransactions = 0;

        if ($dateRange) {
            $previousUsers = User::where('role', 'buyer')
                ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            $previousOrders = Order::whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            // Fixed: Use StoreOrder with status='completed' or 'delivered' for previous period
            $tableName = (new StoreOrder())->getTable();
            $previousCompletedOrders = StoreOrder::whereIn('status', ['completed', 'delivered'])
                ->whereHas('order.user', function ($q) {
                    $q->where('role', 'buyer');
                })
                ->whereBetween($tableName . '.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            try {
                $previousTransactions = Transaction::whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                    ->count();
            } catch (\Exception $e) {
                // Table might not exist yet
            }
        }

        // Calculate percentage increase
        $userIncrease = $this->calculateIncrease($totalUsers, $previousUsers);
        $orderIncrease = $this->calculateIncrease($totalOrders, $previousOrders);
        $completedIncrease = $this->calculateIncrease($completedOrders, $previousCompletedOrders);
        $transactionIncrease = $this->calculateIncrease($totalTransactions, $previousTransactions);

        return [
            'total_users' => [
                'value' => $totalUsers,
                'increase' => $userIncrease,
                'icon' => 'users',
                'color' => 'purple'
            ],
            'total_orders' => [
                'value' => $totalOrders,
                'increase' => $orderIncrease,
                'icon' => 'cart',
                'color' => 'blue'
            ],
            'completed_orders' => [
                'value' => $completedOrders,
                'increase' => $completedIncrease,
                'icon' => 'cart',
                'color' => 'brown'
            ],
            'total_transactions' => [
                'value' => $totalTransactions,
                'increase' => $transactionIncrease,
                'icon' => 'money',
                'color' => 'green'
            ]
        ];
    }

    /**
     * Get seller statistics data
     */
    private function getSellerStats($period = 'all_time')
    {
        $dateRange = $this->getDateRange($period);
        
        // Build queries with period filter
        $sellerQuery = User::where('role', 'seller');
        $storeQuery = Store::query();
        $activeStoreQuery = Store::where('status', 'active');
        $storeOrderQuery = StoreOrder::query();
        // Include both 'delivered' and 'completed' as completed orders
        $completedStoreOrderQuery = StoreOrder::whereIn('status', ['completed', 'delivered']);

        if ($dateRange) {
            $sellerQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            $storeQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            $activeStoreQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            $storeOrderQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            $completedStoreOrderQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $totalSellers = $sellerQuery->count();
        $totalStores = $storeQuery->count();
        $activeStores = $activeStoreQuery->count();
        $totalStoreOrders = $storeOrderQuery->count();
        $completedStoreOrders = $completedStoreOrderQuery->count();

        // Calculate previous period values for increase calculation
        $previousSellers = 0;
        $previousStores = 0;
        $previousActiveStores = 0;
        $previousStoreOrders = 0;
        $previousCompletedStoreOrders = 0;

        if ($dateRange) {
            $previousSellers = User::where('role', 'seller')
                ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            $previousStores = Store::whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            $previousActiveStores = Store::where('status', 'active')
                ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            $previousStoreOrders = StoreOrder::whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            // Include both 'delivered' and 'completed' as completed orders for previous period
            $previousCompletedStoreOrders = StoreOrder::whereIn('status', ['completed', 'delivered'])
                ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
        }

        // Calculate percentage increase
        $sellerIncrease = $this->calculateIncrease($totalSellers, $previousSellers);
        $storeOrderIncrease = $this->calculateIncrease($totalStoreOrders, $previousStoreOrders);
        $completedIncrease = $this->calculateIncrease($completedStoreOrders, $previousCompletedStoreOrders);
        $transactionIncrease = $this->calculateIncrease(0, 0); // Transactions not tracked for sellers yet

        return [
            'total_users' => [
                'value' => $totalSellers,
                'increase' => $sellerIncrease,
                'icon' => 'users',
                'color' => 'purple'
            ],
            'total_orders' => [
                'value' => $totalStoreOrders,
                'increase' => $storeOrderIncrease,
                'icon' => 'cart',
                'color' => 'blue'
            ],
            'completed_orders' => [
                'value' => $completedStoreOrders,
                'increase' => $completedIncrease,
                'icon' => 'cart',
                'color' => 'brown'
            ],
            'total_transactions' => [
                'value' => 0,
                'increase' => $transactionIncrease,
                'icon' => 'money',
                'color' => 'green'
            ]
        ];
    }

    /**
     * Get site statistics (chart data)
     */
    private function getSiteStats($period = 'all_time')
    {
        $dateRange = $this->getDateRange($period);
        
        // For period-based stats, show daily/weekly/monthly breakdown
        if ($dateRange) {
            if ($period === 'today') {
                // Show hourly data for today
                $userData = User::selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                    ->whereDate('created_at', today())
                    ->groupBy('hour')
                    ->pluck('count', 'hour')
                    ->toArray();

                $orderData = Order::selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                    ->whereDate('created_at', today())
                    ->groupBy('hour')
                    ->pluck('count', 'hour')
                    ->toArray();

                $chartData = [];
                for ($i = 0; $i < 24; $i++) {
                    $chartData[] = [
                        'month' => sprintf('%02d:00', $i),
                        'users' => $userData[$i] ?? 0,
                        'orders' => $orderData[$i] ?? 0
                    ];
                }
            } elseif ($period === 'this_week') {
                // Show daily data for this week
                $userData = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->groupBy('date')
                    ->pluck('count', 'date')
                    ->toArray();

                $orderData = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->groupBy('date')
                    ->pluck('count', 'date')
                    ->toArray();

                $chartData = [];
                $startDate = $dateRange['start']->copy();
                for ($i = 0; $i < 7; $i++) {
                    $date = $startDate->copy()->addDays($i);
                    $dateKey = $date->format('Y-m-d');
                    $chartData[] = [
                        'month' => $date->format('D'),
                        'users' => $userData[$dateKey] ?? 0,
                        'orders' => $orderData[$dateKey] ?? 0
                    ];
                }
            } elseif ($period === 'this_month') {
                // Show daily data for this month
                $userData = User::selectRaw('DAY(created_at) as day, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->groupBy('day')
                    ->pluck('count', 'day')
                    ->toArray();

                $orderData = Order::selectRaw('DAY(created_at) as day, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->groupBy('day')
                    ->pluck('count', 'day')
                    ->toArray();

                $chartData = [];
                $daysInMonth = $dateRange['start']->daysInMonth;
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $chartData[] = [
                        'month' => (string)$i,
                        'users' => $userData[$i] ?? 0,
                        'orders' => $orderData[$i] ?? 0
                    ];
                }
            } else {
                // Default to monthly for this_year
                $userData = User::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->groupBy('month')
                    ->pluck('count', 'month')
                    ->toArray();

                $orderData = Order::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->groupBy('month')
                    ->pluck('count', 'month')
                    ->toArray();

                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $chartData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $chartData[] = [
                        'month' => $months[$i - 1],
                        'users' => $userData[$i] ?? 0,
                        'orders' => $orderData[$i] ?? 0
                    ];
                }
            }
        } else {
            // All time - show monthly data for the current year
            $currentYear = date('Y');
            
            $userData = User::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            $orderData = Order::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            // Fill in missing months with 0
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chartData = [];
            
            for ($i = 1; $i <= 12; $i++) {
                $chartData[] = [
                    'month' => $months[$i - 1],
                    'users' => $userData[$i] ?? 0,
                    'orders' => $orderData[$i] ?? 0
                ];
            }
        }

        return [
            'chart_data' => $chartData,
            'legend' => [
                ['label' => 'Users', 'color' => 'green'],
                ['label' => 'Orders', 'color' => 'red']
            ]
        ];
    }

    /**
     * Get latest chats
     */
    private function getLatestChats()
    {
        return Chat::with(['store.user', 'user'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($chat) {
                return [
                    'id' => $chat->id,
                    'store' => [
                        'name' => $chat->store->store_name ?? 'Unknown Store',
                        'profile_image' => $chat->store->profile_image ?? null
                    ],
                    'customer' => [
                        'name' => $chat->user->full_name ?? 'Unknown Customer',
                        'profile_image' => $chat->user->profile_picture ?? null
                    ],
                    'last_message_at' => $chat->updated_at
                ];
            });
    }

    /**
     * Get latest orders
     */
    private function getLatestOrders(Request $request = null)
    {
        $query = StoreOrder::with(['store.user', 'order.user', 'items.product'])
            ->latest();

        if ($request) {
            $status = $request->get('status', 'all');
            $search = $request->get('search', '');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($search) {
                $query->whereHas('store', function ($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%");
                })->orWhereHas('order.user', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%");
                });
            }
        }

        return $query->limit(10)->get()->map(function ($storeOrder) {
            $firstItem = $storeOrder->items->first();
            $totalPrice = $storeOrder->subtotal_with_shipping ?? ($storeOrder->items_subtotal + $storeOrder->shipping_fee) ?? 0;
            return [
                'id' => $storeOrder->id,
                'store_name' => $storeOrder->store ? $storeOrder->store->store_name : 'Unknown Store',
                'buyer_name' => $storeOrder->order && $storeOrder->order->user ? $storeOrder->order->user->full_name : 'Unknown Buyer',
                'product_name' => $firstItem && $firstItem->product ? $firstItem->product->name : 'Unknown Product',
                'price' => number_format($totalPrice, 2),
                'order_date' => $storeOrder->created_at->format('d-m-Y/H:iA'),
                'status' => $storeOrder->status,
                'status_color' => $this->getStatusColor($storeOrder->status)
            ];
        });
    }

    /**
     * Get filtered orders
     */
    private function getFilteredOrders($status, $search)
    {
        $query = StoreOrder::with(['store.user', 'order.user', 'items.product']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('store', function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%");
            })->orWhereHas('order.user', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        return $query->latest()->get()->map(function ($storeOrder) {
            return [
                'id' => $storeOrder->id,
                'store_name' => $storeOrder->store->store_name ?? 'Unknown Store',
                'buyer_name' => $storeOrder->order->user->full_name ?? 'Unknown Buyer',
                'product_name' => $storeOrder->items->first()->product->name ?? 'Unknown Product',
                'price' => number_format($storeOrder->total_amount, 2),
                'order_date' => $storeOrder->created_at->format('d-m-Y/H:iA'),
                'status' => $storeOrder->status,
                'status_color' => $this->getStatusColor($storeOrder->status)
            ];
        });
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor($status)
    {
        $colors = [
            'order_placed' => 'red',
            'out_for_delivery' => 'blue',
            'delivered' => 'purple',
            'completed' => 'green',
            'disputed' => 'orange'
        ];

        return $colors[$status] ?? 'gray';
    }
}
