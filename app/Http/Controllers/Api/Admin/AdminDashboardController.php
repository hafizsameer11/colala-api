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
    public function dashboard()
    {
        try {
            $data = [
                'buyer_stats' => $this->getBuyerStats(),
                'seller_stats' => $this->getSellerStats(),
                'site_stats' => $this->getSiteStats(),
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
    public function buyerStats()
    {
        try {
            $stats = $this->getBuyerStats();
            return ResponseHelper::success($stats, 'Buyer statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller statistics
     */
    public function sellerStats()
    {
        try {
            $stats = $this->getSellerStats();
            return ResponseHelper::success($stats, 'Seller statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get site statistics (chart data)
     */
    public function siteStats()
    {
        try {
            $stats = $this->getSiteStats();
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
     * Get buyer statistics data
     */
    private function getBuyerStats()
    {
        $totalUsers = User::where('role', 'buyer')->count();
        $totalOrders = Order::count();
        $completedOrders = Order::where('payment_status', 'completed')->count();
        $totalTransactions = 0;
        try {
            $totalTransactions = Transaction::count();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Calculate percentage increase (mock data for now)
        $userIncrease = 5;
        $orderIncrease = 5;
        $completedIncrease = 5;
        $transactionIncrease = 5;

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
    private function getSellerStats()
    {
        $totalSellers = User::where('role', 'seller')->count();
        $totalStores = Store::count();
        $activeStores = Store::where('status', 'active')->count();
        $totalStoreOrders = StoreOrder::count();

        // Calculate percentage increase (mock data for now)
        $sellerIncrease = 5;
        $storeIncrease = 5;
        $activeStoreIncrease = 5;
        $storeOrderIncrease = 5;

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
                'value' => StoreOrder::where('status', 'completed')->count(),
                'increase' => $storeOrderIncrease,
                'icon' => 'cart',
                'color' => 'brown'
            ],
            'total_transactions' => [
                'value' => 0,
                'increase' => $storeOrderIncrease,
                'icon' => 'money',
                'color' => 'green'
            ]
        ];
    }

    /**
     * Get site statistics (chart data)
     */
    private function getSiteStats()
    {
        // Get monthly data for the current year
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
