<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserActivity;
use App\Services\UserService;
use App\Services\WalletService;
use App\Traits\PeriodFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    use PeriodFilterTrait;
    protected $userService;
    protected $walletService;

    public function __construct(UserService $userService, WalletService $walletService)
    {
        $this->userService = $userService;
        $this->walletService = $walletService;
    }
    /**
     * Get all users with pagination and search
     */
    public function index(Request $request)
    {
        try {
            $query = User::with('wallet');
            // Allow filtering by role if specified, otherwise show all
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Role filter (keeping for compatibility but defaulting to buyer)
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('is_active', $request->status === 'active');
            }

            $users = $query->latest()->paginate(15);

            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_picture' => $user->profile_picture,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'wallet_balance' => $user->wallet ? number_format($user->wallet->shopping_balance + $user->wallet->reward_balance, 2) : '0.00',
                    'created_at' => $user->created_at->format('d-m-Y H:i:s')
                ];
            });

            // Get stats with period filtering
            $period = $request->get('period', 'all_time');
            $stats = $this->getUserStats($period);

            // Merge stats with pagination data
            $responseData = array_merge($users->toArray(), [
                'stats' => $stats
            ]);

            return ResponseHelper::success($responseData, 'Users retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user statistics
     */
    public function stats(Request $request)
    {
        try {
            $period = $request->get('period', 'all_time');
            $stats = $this->getUserStats($period);
            return ResponseHelper::success($stats, 'User statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user statistics with period filtering
     */
    private function getUserStats($period = 'all_time')
    {
        $dateRange = $this->getDateRange($period);
        
        // Build queries with period filter
        $totalUsersQuery = User::query();
        $activeUsersQuery = User::where('is_active', true);
        $newUsersQuery = User::query(); // New users are those created in the period

        if ($dateRange) {
            // For total users, count all users created within the period
            $totalUsersQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            // For active users, count active users created within the period
            $activeUsersQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            // For new users, count users created within the period (same as total for period-based stats)
            $newUsersQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $totalUsers = $totalUsersQuery->count();
        $activeUsers = $activeUsersQuery->count();
        $newUsers = $newUsersQuery->count();

        // Calculate previous period values for increase calculation
        $previousTotalUsers = 0;
        $previousActiveUsers = 0;
        $previousNewUsers = 0;

        if ($dateRange) {
            // Previous period total users (created within previous period)
            $previousTotalUsers = User::whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            // Previous period active users (created within previous period)
            $previousActiveUsers = User::where('is_active', true)
                ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
            
            // Previous period new users (created within previous period)
            $previousNewUsers = User::whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->count();
        }

        // Calculate percentage increase
        $totalIncrease = $this->calculateIncrease($totalUsers, $previousTotalUsers);
        $activeIncrease = $this->calculateIncrease($activeUsers, $previousActiveUsers);
        $newIncrease = $this->calculateIncrease($newUsers, $previousNewUsers);

        return [
            'total_users' => [
                'value' => $totalUsers,
                'increase' => $totalIncrease,
                'icon' => 'users',
                'color' => 'purple'
            ],
            'active_users' => [
                'value' => $activeUsers,
                'increase' => $activeIncrease,
                'icon' => 'users',
                'color' => 'green'
            ],
            'new_users' => [
                'value' => $newUsers,
                'increase' => $newIncrease,
                'icon' => 'users',
                'color' => 'blue'
            ]
        ];
    }

    /**
     * Search users
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'search' => 'required|string|min:2'
            ]);

            $search = $request->search;
            $users = User::where('role', 'buyer')->with('wallet') // Only buyers
                ->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                })
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'profile_picture' => $user->profile_picture,
                        'wallet_balance' => $user->wallet ? number_format($user->wallet->shopping_balance + $user->wallet->reward_balance, 2) : '0.00'
                    ];
                });

            return ResponseHelper::success($users, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on users
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'user_ids' => 'required|array',
                'action' => 'required|string|in:activate,deactivate,delete'
            ]);

            $userIds = $request->user_ids;
            $action = $request->action;

            if ($action === 'activate') {
                User::where('role', 'buyer')->whereIn('id', $userIds)->update(['is_active' => true]);
                $message = "Users activated successfully";
            } elseif ($action === 'deactivate') {
                User::where('role', 'buyer')->whereIn('id', $userIds)->update(['is_active' => false]);
                $message = "Users deactivated successfully";
            } else {
                User::where('role', 'buyer')->whereIn('id', $userIds)->delete();
                $message = "Users deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user profile with wallet balances and recent activities
     */
    public function showProfile($id)
    {
        try {
            $user = User::where('role', 'buyer')->with(['wallet', 'userActivities' => function($query) {
                $query->latest()->limit(10);
            }])->findOrFail($id);

            $profileData = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'state' => $user->state,
                'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                'last_login' => $user->updated_at->format('d/m/y - h:i A'),
                'account_created_at' => $user->created_at->format('d/m/y - h:i A'),
                'loyalty_points' => $user->wallet ? $user->wallet->loyality_points : 0,
                'is_blocked' => !$user->is_active,
                'wallet' => [
                    'shopping_balance' => $user->wallet ? number_format($user->wallet->shopping_balance, 0) : '0',
                    'escrow_balance' => $user->wallet ? number_format($user->wallet->referral_balance, 0) : '0'
                ],
                'recent_activities' => $user->userActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'description' => $activity->message,
                        'created_at' => $activity->created_at->format('d/m/y - h:i A')
                    ];
                })
            ];

            return ResponseHelper::success($profileData, 'User profile retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user orders
     */
    public function userOrders(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            // Get store orders directly instead of through main orders
            $query = \App\Models\StoreOrder::whereHas('order', function ($q) use ($id) {
                $q->where('user_id', $id);
            })->with(['store', 'order', 'items.product']);

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter to the main query
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $tableName = (new \App\Models\StoreOrder())->getTable();
                    $query->whereBetween($tableName . '.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery->where('store_name', 'like', "%{$search}%");
                    })->orWhereHas('items.product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    })->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_no', 'like', "%{$search}%");
                    });
                });
            }

            $storeOrders = $query->latest()->paginate(15);

            $storeOrders->getCollection()->transform(function ($storeOrder) {
                $product = $storeOrder->items->first();
                
                return [
                    'id' => $storeOrder->id, // StoreOrder ID
                    'order_no' => $storeOrder->order ? $storeOrder->order->order_no : 'N/A',
                    'store_name' => $storeOrder->store ? $storeOrder->store->store_name : 'Deleted Store',
                    'product_name' => $product && $product->product ? $product->product->name : 'Unknown Product',
                    'price' => number_format($storeOrder->subtotal_with_shipping, 2),
                    'order_date' => $storeOrder->created_at->format('d-m-Y H:i:s'),
                    'status' => $storeOrder->status,
                    'status_color' => $this->getOrderStatusColor($storeOrder->status)
                ];
            });

            // Get order statistics for this user
            $orderStats = $this->getUserOrderStats($user);

            return ResponseHelper::success([
                'orders' => $storeOrders,
                'statistics' => $orderStats,
                'pagination' => [
                    'current_page' => $storeOrders->currentPage(),
                    'last_page' => $storeOrders->lastPage(),
                    'per_page' => $storeOrders->perPage(),
                    'total' => $storeOrders->total(),
                ]
            ], 'User orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Filter user orders
     */
    public function filterUserOrders(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            // Get store orders directly instead of through main orders
            $query = \App\Models\StoreOrder::whereHas('order', function ($q) use ($id) {
                $q->where('user_id', $id);
            })->with(['store', 'order', 'items.product']);

            $status = $request->get('status', 'all');
            $search = $request->get('search', '');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter to the main query
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $tableName = (new \App\Models\StoreOrder())->getTable();
                    $query->whereBetween($tableName . '.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery->where('store_name', 'like', "%{$search}%");
                    })->orWhereHas('items.product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    })->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_no', 'like', "%{$search}%");
                    });
                });
            }

            $storeOrders = $query->latest()->get()->map(function ($storeOrder) {
                $product = $storeOrder->items->first();
                
                return [
                    'id' => $storeOrder->id, // StoreOrder ID
                    'order_no' => $storeOrder->order ? $storeOrder->order->order_no : 'N/A',
                    'store_name' => $storeOrder->store ? $storeOrder->store->store_name : 'Deleted Store',
                    'product_name' => $product && $product->product ? $product->product->name : 'Unknown Product',
                    'price' => number_format($storeOrder->subtotal_with_shipping, 2),
                    'order_date' => $storeOrder->created_at->format('d-m-Y H:i:s'),
                    'status' => $storeOrder->status,
                    'status_color' => $this->getOrderStatusColor($storeOrder->status)
                ];
            });

            // Get order statistics for this user with period filtering
            $orderStats = $this->getUserOrderStats($user, $period);

            return ResponseHelper::success([
                'orders' => $storeOrders,
                'statistics' => $orderStats,
                'filters' => [
                    'status' => $status,
                    'search' => $search,
                    'period' => $period ?? 'all_time'
                ]
            ], 'Filtered orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on user orders (using StoreOrder IDs)
     */
    public function bulkOrderAction(Request $request, $id)
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
                
                // Update store orders status
                \App\Models\StoreOrder::whereHas('order', function ($q) use ($id) {
                    $q->where('user_id', $id);
                })->whereIn('id', $orderIds)->update(['status' => $newStatus]);
                $message = "Orders status updated to {$newStatus}";
            } else {
                // Delete store orders
                \App\Models\StoreOrder::whereHas('order', function ($q) use ($id) {
                    $q->where('user_id', $id);
                })->whereIn('id', $orderIds)->delete();
                $message = "Orders deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get complete order details (using StoreOrder ID)
     */
    public function orderDetails($id, $orderId)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            // Find StoreOrder by ID and ensure it belongs to the user
            $storeOrder = \App\Models\StoreOrder::whereHas('order', function ($q) use ($id) {
                $q->where('user_id', $id);
            })->with([
                'store',
                'order.deliveryAddress',
                'items.product.images',
                'items.product.variants',
                'items.variant',
                'items.product.reviews',
                'orderTracking',
                'chat'
            ])->findOrFail($orderId);
            
            // Format the complete order details
            $orderDetails = [
                'id' => $storeOrder->id,
                'order_no' => $storeOrder->order ? $storeOrder->order->order_no : 'N/A',
                'status' => $storeOrder->status,
                'status_color' => $this->getOrderStatusColor($storeOrder->status),
                'store' => $storeOrder->store ? [
                    'id' => $storeOrder->store->id,
                    'name' => $storeOrder->store->store_name,
                    'email' => $storeOrder->store->store_email,
                    'phone' => $storeOrder->store->store_phone,
                    'location' => $storeOrder->store->store_location,
                ] : null,
                'customer' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'delivery_address' => $storeOrder->order->deliveryAddress ? [
                    'id' => $storeOrder->order->deliveryAddress->id,
                    'full_address' => $storeOrder->order->deliveryAddress->full_address,
                    'state' => $storeOrder->order->deliveryAddress->state,
                    'local_government' => $storeOrder->order->deliveryAddress->local_government,
                    'contact_name' => $storeOrder->order->deliveryAddress->contact_name,
                    'contact_phone' => $storeOrder->order->deliveryAddress->contact_phone,
                ] : null,
                'items' => $storeOrder->items->map(function ($item) use ($storeOrder) {
                    return [
                        'id' => $item->id,
                        'complete' => [
                            'product' => [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'description' => $item->product->description,
                                'price' => $item->product->price,
                                'discount_price' => $item->product->discount_price,
                                'quantity' => $item->product->quantity,
                                'status' => $item->product->status,
                                'is_featured' => $item->product->is_featured,
                                'created_at' => $item->product->created_at->format('d-m-Y H:i:s')
                            ],
                            'images' => $item->product->images->map(function ($image) {
                                return [
                                    'id' => $image->id,
                                    'path' => asset('storage/' . $image->path),
                                    'is_main' => $image->is_main,
                                    'type' => $image->type
                                ];
                            }),
                            'variants' => $item->product->variants->map(function ($variant) {
                                return [
                                    'id' => $variant->id,
                                    'name' => $variant->name,
                                    'price' => $variant->price,
                                    'stock' => $variant->stock,
                                    'is_active' => $variant->is_active
                                ];
                            }),
                            'store' => $storeOrder->store ? [
                                'id' => $storeOrder->store->id,
                                'store_name' => $storeOrder->store->store_name,
                                'store_email' => $storeOrder->store->store_email,
                                'store_phone' => $storeOrder->store->store_phone,
                                'store_location' => $storeOrder->store->store_location,
                                'profile_image' => $storeOrder->store->profile_image ? asset('storage/' . $storeOrder->store->profile_image) : null,
                                'banner_image' => $storeOrder->store->banner_image ? asset('storage/' . $storeOrder->store->banner_image) : null,
                                'theme_color' => $storeOrder->store->theme_color,
                                'average_rating' => $storeOrder->store->average_rating,
                                'total_sold' => $storeOrder->store->total_sold,
                                'followers_count' => $storeOrder->store->followers_count
                            ] : null,
                            'reviews' => $item->product->reviews->map(function ($review) {
                                return [
                                    'id' => $review->id,
                                    'user' => [
                                        'id' => $review->user->id,
                                        'name' => $review->user->full_name,
                                        'profile_picture' => $review->user->profile_picture ? asset('storage/' . $review->user->profile_picture) : null
                                    ],
                                    'rating' => $review->rating,
                                    'comment' => $review->comment,
                                    'created_at' => $review->created_at->format('d-m-Y H:i:s')
                                ];
                            })
                        ],
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'images' => $item->product->images->map(function ($image) {
                                return [
                                    'id' => $image->id,
                                    'path' => asset('storage/' . $image->path),
                                    'is_main' => $image->is_main
                                ];
                            })
                        ],
                        'variant' => $item->variant ? [
                            'id' => $item->variant->id,
                            'name' => $item->variant->name,
                            'price' => $item->variant->price
                        ] : null,
                        'quantity' => $item->qty,
                        'price' => $item->price,
                        'total' => $item->price * $item->qty
                    ];
                }),
                'pricing' => [
                    'items_subtotal' => $storeOrder->items_subtotal,
                    'shipping_fee' => $storeOrder->shipping_fee,
                    'discount' => $storeOrder->discount,
                    'subtotal_with_shipping' => $storeOrder->subtotal_with_shipping,
                ],
                'tracking' => $storeOrder->orderTracking->map(function ($tracking) {
                    return [
                        'id' => $tracking->id,
                        'status' => $tracking->status,
                        'description' => $tracking->description,
                        'location' => $tracking->location,
                        'created_at' => $tracking->created_at->format('d-m-Y H:i:s')
                    ];
                }),
                'chat' => $storeOrder->chat ? [
                    'id' => $storeOrder->chat->id,
                    'is_dispute' => $storeOrder->chat->is_dispute ?? false,
                    'last_message' => $storeOrder->chat->messages()->latest()->first()?->message
                ] : null,
                'created_at' => $storeOrder->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $storeOrder->updated_at->format('d-m-Y H:i:s')
            ];

            return ResponseHelper::success($orderDetails, 'Complete order details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update order status (using StoreOrder ID)
     */
    public function updateOrderStatus(Request $request, $id, $orderId)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:order_placed,out_for_delivery,delivered,completed,disputed,uncompleted'
            ]);

            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            // Find StoreOrder by ID and ensure it belongs to the user
            $storeOrder = \App\Models\StoreOrder::whereHas('order', function ($q) use ($id) {
                $q->where('user_id', $id);
            })->findOrFail($orderId);
            
            // Update store order status
            $storeOrder->update(['status' => $request->status]);

            return ResponseHelper::success([
                'order_id' => $storeOrder->id,
                'new_status' => $request->status,
                'status_color' => $this->getOrderStatusColor($request->status)
            ], 'Order status updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get order status color
     */
    private function getOrderStatusColor($status)
    {
        $colors = [
            'order_placed' => 'red',
            'out_for_delivery' => 'blue',
            'delivered' => 'purple',
            'completed' => 'green',
            'disputed' => 'red',
            'uncompleted' => 'gray'
        ];

        return $colors[$status] ?? 'gray';
    }

    /**
     * Get user order statistics (based on StoreOrders) with period filtering
     */
    private function getUserOrderStats($user, $period = 'all_time')
    {
        // Get StoreOrders for this user
        $storeOrdersQuery = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        $pendingOrdersQuery = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->whereIn('status', ['pending', 'processing', 'shipped', 'pending_acceptance', 'order_placed']);

        $completedOrdersQuery = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'completed');

        // Apply period filter if provided
        if ($period && $period !== 'all_time' && $period !== 'null') {
            $dateRange = $this->getDateRange($period);
            if ($dateRange) {
                $tableName = (new \App\Models\StoreOrder())->getTable();
                $storeOrdersQuery->whereBetween($tableName . '.created_at', [$dateRange['start'], $dateRange['end']]);
                $pendingOrdersQuery->whereBetween($tableName . '.created_at', [$dateRange['start'], $dateRange['end']]);
                $completedOrdersQuery->whereBetween($tableName . '.created_at', [$dateRange['start'], $dateRange['end']]);
            }
        }

        $totalOrders = $storeOrdersQuery->count();
        $pendingOrders = $pendingOrdersQuery->count();
        $completedOrders = $completedOrdersQuery->count();

        // Calculate percentage increase based on period
        $totalIncrease = 0;
        $pendingIncrease = 0;
        $completedIncrease = 0;

        if ($period && $period !== 'all_time' && $period !== 'null') {
            $dateRange = $this->getDateRange($period);
            if ($dateRange) {
                $tableName = (new \App\Models\StoreOrder())->getTable();
                
                // Previous period queries
                $previousTotalOrdersQuery = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->whereBetween($tableName . '.created_at', [$dateRange['previous_start'], $dateRange['previous_end']]);

                $previousPendingOrdersQuery = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->whereIn('status', ['pending', 'processing', 'shipped', 'pending_acceptance', 'order_placed'])
                  ->whereBetween($tableName . '.created_at', [$dateRange['previous_start'], $dateRange['previous_end']]);

                $previousCompletedOrdersQuery = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->where('status', 'completed')
                  ->whereBetween($tableName . '.created_at', [$dateRange['previous_start'], $dateRange['previous_end']]);

                $previousTotalOrders = $previousTotalOrdersQuery->count();
                $previousPendingOrders = $previousPendingOrdersQuery->count();
                $previousCompletedOrders = $previousCompletedOrdersQuery->count();

                $totalIncrease = $this->calculateIncrease($totalOrders, $previousTotalOrders);
                $pendingIncrease = $this->calculateIncrease($pendingOrders, $previousPendingOrders);
                $completedIncrease = $this->calculateIncrease($completedOrders, $previousCompletedOrders);
            }
        } else {
            // Default: calculate increase from last month
            $lastMonth = now()->subMonth();
            $currentMonth = now();
            
            $lastMonthOrders = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->whereBetween('created_at', [
                $lastMonth->startOfMonth(),
                $lastMonth->endOfMonth()
            ])->count();
            
            $currentMonthOrders = \App\Models\StoreOrder::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->whereBetween('created_at', [
                $currentMonth->startOfMonth(),
                $currentMonth->endOfMonth()
            ])->count();

            $totalIncrease = $this->calculateIncrease($currentMonthOrders, $lastMonthOrders);
            $pendingIncrease = 5; // Mock data for pending orders increase
            $completedIncrease = 5; // Mock data for completed orders increase
        }

        return [
            'total_orders' => [
                'value' => $totalOrders,
                'increase' => $totalIncrease,
                'icon' => 'shopping-cart',
                'color' => 'blue',
                'label' => 'Total Orders'
            ],
            'pending_orders' => [
                'value' => $pendingOrders,
                'increase' => $pendingIncrease,
                'icon' => 'clock',
                'color' => 'yellow',
                'label' => 'Pending Orders'
            ],
            'completed_orders' => [
                'value' => $completedOrders,
                'increase' => $completedIncrease,
                'icon' => 'check-circle',
                'color' => 'green',
                'label' => 'Completed Orders'
            ]
        ];
    }

    /**
     * Get user chat statistics with period filtering
     */
    private function getUserChatStats($user, $period = 'all_time')
    {
        $totalChatsQuery = $user->chats();
        $unreadChatsQuery = $user->chats()->whereHas('messages', function ($q) {
            $q->where('is_read', false);
        });
        $disputeChatsQuery = $user->chats()->whereHas('dispute');

        // Apply period filter if provided
        if ($period && $period !== 'all_time' && $period !== 'null') {
            $dateRange = $this->getDateRange($period);
            if ($dateRange) {
                $totalChatsQuery->whereBetween('chats.created_at', [$dateRange['start'], $dateRange['end']]);
                $unreadChatsQuery->whereBetween('chats.created_at', [$dateRange['start'], $dateRange['end']]);
                $disputeChatsQuery->whereBetween('chats.created_at', [$dateRange['start'], $dateRange['end']]);
            }
        }

        $totalChats = $totalChatsQuery->count();
        $unreadChats = $unreadChatsQuery->count();
        $disputeChats = $disputeChatsQuery->count();

        // Calculate percentage increase based on period
        $totalIncrease = 0;
        $unreadIncrease = 0;
        $disputeIncrease = 0;

        if ($period && $period !== 'all_time' && $period !== 'null') {
            $dateRange = $this->getDateRange($period);
            if ($dateRange) {
                // Previous period queries
                $previousTotalChats = $user->chats()
                    ->whereBetween('chats.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                    ->count();
                
                $previousUnreadChats = $user->chats()
                    ->whereHas('messages', function ($q) {
                        $q->where('is_read', false);
                    })
                    ->whereBetween('chats.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                    ->count();
                
                $previousDisputeChats = $user->chats()
                    ->whereHas('dispute')
                    ->whereBetween('chats.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                    ->count();

                $totalIncrease = $this->calculateIncrease($totalChats, $previousTotalChats);
                $unreadIncrease = $this->calculateIncrease($unreadChats, $previousUnreadChats);
                $disputeIncrease = $this->calculateIncrease($disputeChats, $previousDisputeChats);
            }
        } else {
            // Default: calculate increase from last month
            $lastMonth = now()->subMonth();
            $currentMonth = now();
            
            $lastMonthChats = $user->chats()->whereBetween('created_at', [
                $lastMonth->startOfMonth(),
                $lastMonth->endOfMonth()
            ])->count();
            
            $currentMonthChats = $user->chats()->whereBetween('created_at', [
                $currentMonth->startOfMonth(),
                $currentMonth->endOfMonth()
            ])->count();

            $totalIncrease = $this->calculateIncrease($currentMonthChats, $lastMonthChats);
            $unreadIncrease = 5; // Mock data for unread chats increase
            $disputeIncrease = 5; // Mock data for dispute chats increase
        }

        return [
            'total_chats' => [
                'value' => $totalChats,
                'increase' => $totalIncrease,
                'icon' => 'message-circle',
                'color' => 'blue',
                'label' => 'Total Chats'
            ],
            'unread_chats' => [
                'value' => $unreadChats,
                'increase' => $unreadIncrease,
                'icon' => 'mail',
                'color' => 'red',
                'label' => 'Unread Chats'
            ],
            'dispute_chats' => [
                'value' => $disputeChats,
                'increase' => $disputeIncrease,
                'icon' => 'alert-triangle',
                'color' => 'orange',
                'label' => 'Dispute'
            ]
        ];
    }

    /**
     * Get user chats
     */
    public function userChats(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            $query = $user->chats()->with(['store', 'messages' => function($query) {
                $query->latest()->limit(1);
            }]);

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                if ($request->status === 'unread') {
                    $query->whereHas('messages', function ($q) {
                        $q->where('is_read', false);
                    });
                } elseif ($request->status === 'dispute') {
                    $query->whereHas('dispute');
                }
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter to the main query
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $query->whereBetween('chats.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('store', function ($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%");
                });
            }

            $chats = $query->latest()->paginate(15);

            $chats->getCollection()->transform(function ($chat) {
                $lastMessage = $chat->messages->first();
                return [
                    'id' => $chat->id,
                    'store_name' => $chat->store->store_name ?? 'Unknown Store',
                    'store_image' => $chat->store->profile_image ?? null,
                    'user_name' => $chat->user->full_name ?? 'Unknown User',
                    'last_message' => $lastMessage ? $lastMessage->message : 'No messages',
                    'last_message_time' => $lastMessage ? $lastMessage->created_at->format('d-m-Y/h:iA') : null,
                    'is_read' => $lastMessage ? $lastMessage->is_read : true,
                    'is_dispute' => $chat->dispute ? true : false,
                    'chat_date' => $chat->created_at->format('d-m-Y/h:iA'),
                    'unread_count' => $chat->messages()->where('is_read', false)->count()
                ];
            });

            // Get chat statistics for this user
            $chatStats = $this->getUserChatStats($user);

            return ResponseHelper::success([
                'chats' => $chats,
                'statistics' => $chatStats,
                'pagination' => [
                    'current_page' => $chats->currentPage(),
                    'last_page' => $chats->lastPage(),
                    'per_page' => $chats->perPage(),
                    'total' => $chats->total(),
                ]
            ], 'User chats retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Filter user chats
     */
    public function filterUserChats(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            $query = $user->chats()->with(['store', 'messages' => function($query) {
                $query->latest()->limit(1);
            }]);

            $status = $request->get('status', 'all');
            $search = $request->get('search', '');

            if ($status !== 'all') {
                if ($status === 'unread') {
                    $query->whereHas('messages', function ($q) {
                        $q->where('is_read', false);
                    });
                } elseif ($status === 'dispute') {
                    $query->whereHas('dispute');
                }
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter to the main query
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $query->whereBetween('chats.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            }

            if ($search) {
                $query->whereHas('store', function ($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%");
                });
            }

            $chats = $query->latest()->get()->map(function ($chat) {
                $lastMessage = $chat->messages->first();
                return [
                    'id' => $chat->id,
                    'store_name' => $chat->store->store_name ?? 'Unknown Store',
                    'store_image' => $chat->store->profile_image ?? null,
                    'user_name' => $chat->user->full_name ?? 'Unknown User',
                    'last_message' => $lastMessage ? $lastMessage->message : 'No messages',
                    'last_message_time' => $lastMessage ? $lastMessage->created_at->format('d-m-Y/h:iA') : null,
                    'is_read' => $lastMessage ? $lastMessage->is_read : true,
                    'is_dispute' => $chat->dispute ? true : false,
                    'chat_date' => $chat->created_at->format('d-m-Y/h:iA'),
                    'unread_count' => $chat->messages()->where('is_read', false)->count()
                ];
            });

            // Get chat statistics for this user with period filtering
            $chatStats = $this->getUserChatStats($user, $period);

            return ResponseHelper::success([
                'chats' => $chats,
                'statistics' => $chatStats,
                'filters' => [
                    'status' => $status,
                    'search' => $search,
                    'period' => $period ?? 'all_time'
                ]
            ], 'Filtered chats retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on user chats
     */
    public function bulkChatAction(Request $request, $id)
    {
        try {
            $request->validate([
                'chat_ids' => 'required|array',
                'action' => 'required|string|in:mark_read,mark_unread,delete,mark_dispute'
            ]);

            $chatIds = $request->chat_ids;
            $action = $request->action;

            if ($action === 'mark_read') {
                \App\Models\ChatMessage::whereIn('chat_id', $chatIds)->update(['is_read' => true]);
                $message = "Chats marked as read";
            } elseif ($action === 'mark_unread') {
                \App\Models\ChatMessage::whereIn('chat_id', $chatIds)->update(['is_read' => false]);
                $message = "Chats marked as unread";
            } elseif ($action === 'mark_dispute') {
                // Create dispute records for the chats
                foreach ($chatIds as $chatId) {
                    \App\Models\Dispute::create([
                        'chat_id' => $chatId,
                        'user_id' => $id,
                        'category' => 'admin_marked',
                        'details' => 'Marked as dispute by admin',
                        'status' => 'open'
                    ]);
                }
                $message = "Chats marked as disputes";
            } else {
                \App\Models\Chat::whereIn('id', $chatIds)->delete();
                $message = "Chats deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get chat details with messages and order information
     */
    public function chatDetails($id, $chatId)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $chat = $user->chats()->with([
                'store',
                'messages' => function($query) {
                    $query->latest();
                },
                'storeOrder.order.storeOrders.items.product'
            ])->findOrFail($chatId);

            $chatDetails = [
                'id' => $chat->id,
                'store' => [
                    'id' => $chat->store->id,
                    'name' => $chat->store->store_name,
                    'email' => $chat->store->store_email,
                    'phone' => $chat->store->store_phone,
                    'profile_image' => $chat->store->profile_image ? asset('storage/' . $chat->store->profile_image) : null
                ],
                'user' => [
                    'id' => $chat->user->id,
                    'name' => $chat->user->full_name,
                    'email' => $chat->user->email,
                    'phone' => $chat->user->phone,
                    'profile_image' => $chat->user->profile_picture ? asset('storage/' . $chat->user->profile_picture) : null
                ],
                'order' => $chat->storeOrder ? [
                    'id' => $chat->storeOrder->order->id,
                    'order_no' => $chat->storeOrder->order->order_no,
                    'status' => $chat->storeOrder->status ?? 'unknown',
                    'total_amount' => number_format($chat->storeOrder->order->grand_total, 2),
                    'items' => $chat->storeOrder->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product->name,
                            'quantity' => $item->qty,
                            'price' => number_format($item->price, 2),
                            'total' => number_format($item->price * $item->qty, 2),
                            'product_image' => $item->product->images->first() ? 
                                asset('storage/' . $item->product->images->first()->path) : null
                        ];
                    })
                ] : null,
                'messages' => $chat->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_type' => $message->sender_type, // 'user' or 'store'
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at->format('h:iA'),
                        'date' => $message->created_at->format('d-m-Y')
                    ];
                }),
                'is_dispute' => $chat->dispute ? true : false,
                'created_at' => $chat->created_at->format('d-m-Y h:iA'),
                'updated_at' => $chat->updated_at->format('d-m-Y h:iA')
            ];

            return ResponseHelper::success($chatDetails, 'Chat details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send message in chat
     */
    public function sendMessage(Request $request, $id, $chatId)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000'
            ]);

            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $chat = $user->chats()->findOrFail($chatId);

            $message = \App\Models\ChatMessage::create([
                'chat_id' => $chat->id,
                'message' => $request->message,
                'sender_type' => 'store', // Admin sending message (using store as admin)
                'is_read' => false
            ]);

            return ResponseHelper::success($message, 'Message sent successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user details
     */
    public function userDetails($id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->with(['wallet', 'orders', 'transactions'])
                ->findOrFail($id);

            $userData = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_picture' => $user->profile_picture,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'country' => $user->country,
                'state' => $user->state,
                'user_code' => $user->user_code,
                'referral_code' => $user->referral_code,
                'wallet' => $user->wallet ? [
                    'shopping_balance' => number_format($user->wallet->shopping_balance, 2),
                    'reward_balance' => number_format($user->wallet->reward_balance, 2),
                    'referral_balance' => number_format($user->wallet->referral_balance, 2),
                    'loyalty_points' => $user->wallet->loyality_points
                ] : null,
                'total_orders' => $user->orders->count(),
                'total_transactions' => $user->transactions->count(),
                'created_at' => $user->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $user->updated_at->format('d-m-Y H:i:s')
            ];

            return ResponseHelper::success($userData, 'User details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user transactions with summary stats
     */
    public function userTransactions(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            $query = Transaction::where('user_id', $id);

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Type filter
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter (priority over date for backward compatibility)
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $query->whereBetween('transactions.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            } elseif ($request->has('date') && $request->date !== 'all') {
                // Legacy support for date parameter
                if ($request->date === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($request->date === 'week') {
                    $query->whereBetween('created_at', [now()->subWeek(), now()]);
                } elseif ($request->date === 'month') {
                    $query->whereMonth('created_at', now()->month);
                }
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tx_id', 'like', "%{$search}%")
                      ->orWhere('amount', 'like', "%{$search}%");
                });
            }

            $transactions = $query->latest()->paginate(15);

            // Get summary stats with period filtering
            $allTransactionsQuery = Transaction::where('user_id', $id);
            $pendingTransactionsQuery = Transaction::where('user_id', $id)->where('status', 'pending');
            $successfulTransactionsQuery = Transaction::where('user_id', $id)->where('status', 'successful');
            $failedTransactionsQuery = Transaction::where('user_id', $id)->where('status', 'failed');

            if ($period && $period !== 'all_time' && $period !== 'null') {
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $allTransactionsQuery->whereBetween('transactions.created_at', [$dateRange['start'], $dateRange['end']]);
                    $pendingTransactionsQuery->whereBetween('transactions.created_at', [$dateRange['start'], $dateRange['end']]);
                    $successfulTransactionsQuery->whereBetween('transactions.created_at', [$dateRange['start'], $dateRange['end']]);
                    $failedTransactionsQuery->whereBetween('transactions.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            }

            $allTransactions = $allTransactionsQuery->count();
            $pendingTransactions = $pendingTransactionsQuery->count();
            $successfulTransactions = $successfulTransactionsQuery->count();
            $failedTransactions = $failedTransactionsQuery->count();

            // Calculate percentage increases
            $allIncrease = 0;
            $pendingIncrease = 0;
            $successfulIncrease = 0;

            if ($period && $period !== 'all_time' && $period !== 'null') {
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $previousAllTransactions = Transaction::where('user_id', $id)
                        ->whereBetween('transactions.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                        ->count();
                    $previousPendingTransactions = Transaction::where('user_id', $id)
                        ->where('status', 'pending')
                        ->whereBetween('transactions.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                        ->count();
                    $previousSuccessfulTransactions = Transaction::where('user_id', $id)
                        ->where('status', 'successful')
                        ->whereBetween('transactions.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                        ->count();

                    $allIncrease = $this->calculateIncrease($allTransactions, $previousAllTransactions);
                    $pendingIncrease = $this->calculateIncrease($pendingTransactions, $previousPendingTransactions);
                    $successfulIncrease = $this->calculateIncrease($successfulTransactions, $previousSuccessfulTransactions);
                }
            }

            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => 'N' . number_format($transaction->amount, 0),
                    'type' => ucfirst($transaction->type),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'tx_date' => $transaction->created_at->format('d-m-Y/h:iA'),
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s')
                ];
            });

            $summaryStats = [
                'all_transactions' => [
                    'count' => $allTransactions,
                    'increase' => $allIncrease,
                    'color' => 'red'
                ],
                'pending_transactions' => [
                    'count' => $pendingTransactions,
                    'increase' => $pendingIncrease,
                    'color' => 'red'
                ],
                'successful_transactions' => [
                    'count' => $successfulTransactions,
                    'increase' => $successfulIncrease,
                    'color' => 'red'
                ]
            ];

            return ResponseHelper::success([
                'transactions' => $transactions,
                'summary_stats' => $summaryStats
            ], 'User transactions retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Filter user transactions
     */
    public function filterUserTransactions(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            $query = Transaction::where('user_id', $id);

            $status = $request->get('status', 'all');
            $type = $request->get('type', 'all');
            $date = $request->get('date', 'all');
            $search = $request->get('search', '');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($type !== 'all') {
                $query->where('type', $type);
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter (priority over date for backward compatibility)
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $query->whereBetween('transactions.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            } elseif ($date !== 'all') {
                // Legacy support for date parameter
                if ($date === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($date === 'week') {
                    $query->whereBetween('created_at', [now()->subWeek(), now()]);
                } elseif ($date === 'month') {
                    $query->whereMonth('created_at', now()->month);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tx_id', 'like', "%{$search}%")
                      ->orWhere('amount', 'like', "%{$search}%");
                });
            }

            $transactions = $query->latest()->get()->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => 'N' . number_format($transaction->amount, 0),
                    'type' => ucfirst($transaction->type),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'tx_date' => $transaction->created_at->format('d-m-Y/h:iA'),
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($transactions, 'Filtered transactions retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on user transactions
     */
    public function bulkTransactionAction(Request $request, $id)
    {
        try {
            $request->validate([
                'transaction_ids' => 'required|array',
                'action' => 'required|string|in:approve,reject,delete'
            ]);

            $transactionIds = $request->transaction_ids;
            $action = $request->action;

            if ($action === 'approve') {
                Transaction::whereIn('id', $transactionIds)->update(['status' => 'successful']);
                $message = "Transactions approved successfully";
            } elseif ($action === 'reject') {
                Transaction::whereIn('id', $transactionIds)->update(['status' => 'failed']);
                $message = "Transactions rejected successfully";
            } else {
                Transaction::whereIn('id', $transactionIds)->delete();
                $message = "Transactions deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get transaction details
     */
    public function transactionDetails($id, $transactionId)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $transaction = Transaction::where('user_id', $id)->findOrFail($transactionId);

            $transactionDetails = [
                'id' => $transaction->id,
                'tx_id' => $transaction->tx_id,
                'amount' => [
                    'formatted' => 'N' . number_format($transaction->amount, 0),
                    'raw' => $transaction->amount,
                    'sign' => $transaction->type === 'deposit' ? '+' : '-'
                ],
                'type' => ucfirst($transaction->type),
                'status' => ucfirst($transaction->status),
                'status_color' => $this->getTransactionStatusColor($transaction->status),
                'channel' => 'Flutterwave', // Transaction model doesn't have payment_method field
                'description' => 'Transaction', // Transaction model doesn't have description field
                'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                'time' => $transaction->created_at->format('F d, Y - h:i A'),
                'date' => $transaction->created_at->format('d-m-Y'),
                'created_at' => $transaction->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $transaction->updated_at->format('d-m-Y H:i:s'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone
                ]
            ];

            return ResponseHelper::success($transactionDetails, 'Transaction details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get transaction status color
     */
    private function getTransactionStatusColor($status)
    {
        $colors = [
            'successful' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'cancelled' => 'gray'
        ];

        return $colors[$status] ?? 'gray';
    }

    /**
     * Get user posts with summary stats
     */
    public function userPosts(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            $query = \App\Models\Post::where('user_id', $id);

            // Type filter
            if ($request->has('type') && $request->type !== 'all') {
                if ($request->type === 'liked_posts') {
                    $query->whereHas('likes', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                } elseif ($request->type === 'comments') {
                    $query->whereHas('comments', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                } elseif ($request->type === 'saved') {
                    $query->whereHas('shares', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                }
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter (priority over date for backward compatibility)
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $query->whereBetween('posts.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            } elseif ($request->has('date') && $request->date !== 'all') {
                // Legacy support for date parameter
                if ($request->date === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($request->date === 'week') {
                    $query->whereBetween('created_at', [now()->subWeek(), now()]);
                } elseif ($request->date === 'month') {
                    $query->whereMonth('created_at', now()->month);
                }
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('content', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%");
                });
            }

            $posts = $query->with(['user', 'media', 'likes', 'comments', 'shares'])
                ->latest()
                ->paginate(15);

            // Get summary stats with period filtering
            $leadPostsQuery = \App\Models\Post::where('user_id', $id);
            $commentsQuery = \App\Models\PostComment::where('user_id', $id);
            $savedPostsQuery = \App\Models\PostShare::where('user_id', $id);

            if ($period && $period !== 'all_time' && $period !== 'null') {
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $leadPostsQuery->whereBetween('posts.created_at', [$dateRange['start'], $dateRange['end']]);
                    $commentsQuery->whereBetween('post_comments.created_at', [$dateRange['start'], $dateRange['end']]);
                    $savedPostsQuery->whereBetween('post_shares.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            }

            $leadPosts = $leadPostsQuery->count();
            $comments = $commentsQuery->count();
            $savedPosts = $savedPostsQuery->count();

            // Calculate percentage increases
            $leadPostsIncrease = 0;
            $commentsIncrease = 0;
            $savedPostsIncrease = 0;

            if ($period && $period !== 'all_time' && $period !== 'null') {
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $previousLeadPosts = \App\Models\Post::where('user_id', $id)
                        ->whereBetween('posts.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                        ->count();
                    $previousComments = \App\Models\PostComment::where('user_id', $id)
                        ->whereBetween('post_comments.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                        ->count();
                    $previousSavedPosts = \App\Models\PostShare::where('user_id', $id)
                        ->whereBetween('post_shares.created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                        ->count();

                    $leadPostsIncrease = $this->calculateIncrease($leadPosts, $previousLeadPosts);
                    $commentsIncrease = $this->calculateIncrease($comments, $previousComments);
                    $savedPostsIncrease = $this->calculateIncrease($savedPosts, $previousSavedPosts);
                }
            }

            $posts->getCollection()->transform(function ($post) {
                return [
                    'id' => $post->id,
                    'store_name' => $post->user->full_name ?? 'Unknown User',
                    'type' => $this->getPostActivityType($post),
                    'post' => [
                        'id' => $post->id,
                        'content' => $post->body,
                        'title' => 'Post', // Post model doesn't have title field
                        'media' => $post->media->map(function ($media) {
                            return [
                                'id' => $media->id,
                                'type' => $media->type,
                                'path' => asset('storage/' . $media->path),
                                'url' => $media->url
                            ];
                        }),
                        'likes_count' => $post->likes->count(),
                        'comments_count' => $post->comments->count(),
                        'shares_count' => $post->shares->count()
                    ],
                    'date' => $post->created_at->format('d-m-Y / h:i A'),
                    'created_at' => $post->created_at->format('d-m-Y H:i:s')
                ];
            });

            $summaryStats = [
                'lead_posts' => [
                    'count' => $leadPosts,
                    'increase' => $leadPostsIncrease,
                    'color' => 'red'
                ],
                'comments' => [
                    'count' => $comments,
                    'increase' => $commentsIncrease,
                    'color' => 'red'
                ],
                'saved_posts' => [
                    'count' => $savedPosts,
                    'increase' => $savedPostsIncrease,
                    'color' => 'red'
                ]
            ];

            return ResponseHelper::success([
                'posts' => $posts,
                'summary_stats' => $summaryStats
            ], 'User posts retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Filter user posts
     */
    public function filterUserPosts(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            $query = \App\Models\Post::where('user_id', $id);

            $type = $request->get('type', 'all');
            $date = $request->get('date', 'all');
            $search = $request->get('search', '');

            if ($type !== 'all') {
                if ($type === 'liked_posts') {
                    $query->whereHas('likes', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                } elseif ($type === 'comments') {
                    $query->whereHas('comments', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                } elseif ($type === 'saved') {
                    $query->whereHas('shares', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                }
            }

            // Validate and apply period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null') {
                if (!$this->isValidPeriod($period)) {
                    return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
                }
                // Apply period filter (priority over date for backward compatibility)
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $query->whereBetween('posts.created_at', [$dateRange['start'], $dateRange['end']]);
                }
            } elseif ($date !== 'all') {
                // Legacy support for date parameter
                if ($date === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($date === 'week') {
                    $query->whereBetween('created_at', [now()->subWeek(), now()]);
                } elseif ($date === 'month') {
                    $query->whereMonth('created_at', now()->month);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('content', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%");
                });
            }

            $posts = $query->with(['user', 'media', 'likes', 'comments', 'shares'])
                ->latest()
                ->get()
                ->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'store_name' => $post->user->full_name ?? 'Unknown User',
                        'type' => $this->getPostActivityType($post),
                        'post' => [
                            'id' => $post->id,
                            'content' => $post->body,
                            'title' => 'Post', // Post model doesn't have title field
                            'media' => $post->media->map(function ($media) {
                                return [
                                    'id' => $media->id,
                                    'type' => $media->type,
                                    'path' => asset('storage/' . $media->path),
                                    'url' => $media->url
                                ];
                            }),
                            'likes_count' => $post->likes->count(),
                            'comments_count' => $post->comments->count(),
                            'shares_count' => $post->shares->count()
                        ],
                        'date' => $post->created_at->format('d-m-Y / h:i A'),
                        'created_at' => $post->created_at->format('d-m-Y H:i:s')
                    ];
                });

            return ResponseHelper::success($posts, 'Filtered posts retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on user posts
     */
    public function bulkPostAction(Request $request, $id)
    {
        try {
            $request->validate([
                'post_ids' => 'required|array',
                'action' => 'required|string|in:delete,approve,reject'
            ]);

            $postIds = $request->post_ids;
            $action = $request->action;

            if ($action === 'delete') {
                \App\Models\Post::whereIn('id', $postIds)->delete();
                $message = "Posts deleted successfully";
            } elseif ($action === 'approve') {
                \App\Models\Post::whereIn('id', $postIds)->update(['visibility' => 'public']);
                $message = "Posts approved successfully";
            } else {
                \App\Models\Post::whereIn('id', $postIds)->update(['visibility' => 'followers']); // Post model enum only allows 'public' or 'followers'
                $message = "Posts rejected successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get post details with comments
     */
    public function postDetails($id, $postId)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $post = \App\Models\Post::with([
                'user',
                'media',
                'likes',
                'comments' => function($query) {
                    $query->with('user')->latest();
                },
                'shares'
            ])->findOrFail($postId);

            $postDetails = [
                'id' => $post->id,
                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->full_name,
                    'location' => $post->user->state . ', ' . $post->user->country,
                    'profile_image' => $post->user->profile_picture ? asset('storage/' . $post->user->profile_picture) : null,
                    'time_ago' => $post->created_at->diffForHumans()
                ],
                'content' => [
                    'title' => 'Post', // Post model doesn't have title field
                    'description' => $post->body,
                    'media' => $post->media->map(function ($media) {
                        return [
                            'id' => $media->id,
                            'type' => $media->type,
                            'path' => asset('storage/' . $media->path),
                            'url' => $media->url
                        ];
                    })
                ],
                'engagement' => [
                    'likes' => $post->likes->count(),
                    'comments' => $post->comments->count(),
                    'shares' => $post->shares->count()
                ],
                'comments' => $post->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->full_name,
                            'profile_image' => $comment->user->profile_picture ? asset('storage/' . $comment->user->profile_picture) : null
                        ],
                        'content' => $comment->body,
                        'time_ago' => $comment->created_at->diffForHumans(),
                        'replies_count' => $comment->replies ? $comment->replies->count() : 0
                    ];
                }),
                'created_at' => $post->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $post->updated_at->format('d-m-Y H:i:s')
            ];

            return ResponseHelper::success($postDetails, 'Post details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete post
     */
    public function deletePost($id, $postId)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $post = \App\Models\Post::where('user_id', $id)->findOrFail($postId);
            
            $post->delete();

            return ResponseHelper::success(null, 'Post deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get post comments
     */
    public function postComments($id, $postId)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $post = \App\Models\Post::where('user_id', $id)->findOrFail($postId);
            
            $comments = \App\Models\PostComment::where('post_id', $postId)
                ->with('user')
                ->latest()
                ->paginate(20);

            $comments->getCollection()->transform(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->full_name,
                        'profile_image' => $comment->user->profile_picture ? asset('storage/' . $comment->user->profile_picture) : null
                    ],
                    'content' => $comment->body,
                    'time_ago' => $comment->created_at->diffForHumans(),
                    'created_at' => $comment->created_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($comments, 'Post comments retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete comment
     */
    public function deleteComment($id, $postId, $commentId)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $post = \App\Models\Post::where('user_id', $id)->findOrFail($postId);
            $comment = \App\Models\PostComment::where('post_id', $postId)->findOrFail($commentId);
            
            $comment->delete();

            return ResponseHelper::success(null, 'Comment deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get post activity type
     */
    private function getPostActivityType($post)
    {
        // This would need to be determined based on user's interaction with the post
        // For now, returning a default type
        return 'Post Like';
    }

    /**
     * Create new user (following same pattern as registration)
     */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|max:255',
                'user_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20',
                'password' => 'required|string|min:6',
                'country' => 'required|string',
                'state' => 'required|string',
                'role' => 'nullable|in:admin,moderator,super_admin',
                'referral_code' => 'nullable|string',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $data = $request->all();
            $data['password'] = Hash::make($data['password']);
            $data['user_code'] = $this->userService->createUserCode($data['full_name']);
            $data['role'] = $data['role'] ?? 'buyer'; // Default to buyer if not specified
            $data['is_active'] = true;

            // Use the same user service as registration
            $user = $this->userService->create($data);
            
            // Create wallet for user (same as registration)
            $wallet = $this->walletService->create(['user_id' => $user->id]);

            return ResponseHelper::success($user, 'User created successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'full_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'phone' => 'sometimes|string|max:20',
                'password' => 'sometimes|string|min:8',
                'role' => 'sometimes|in:buyer,seller',
                'is_active' => 'sometimes|boolean',
                'country' => 'nullable|string',
                'state' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $updateData = $request->all();
            
            if (isset($updateData['password'])) {
                $updateData['password'] = Hash::make($updateData['password']);
            }

            $user->update($updateData);

            return ResponseHelper::success($user->fresh(), 'User updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete user
     */
    public function delete($id)
    {
        try {
            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $user->delete();

            return ResponseHelper::success(null, 'User deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Top up user wallet (Admin can top up for any user)
     */
    public function topUp(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1',
                'description' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            $wallet = $this->walletService->topUp($user->id, $request->amount);

            // Log admin activity
            UserActivity::create([
                'user_id' => $user->id,
                'message' => "Wallet topped up by admin: ₦{$request->amount}" . ($request->description ? " - {$request->description}" : ''),
            ]);

            return ResponseHelper::success([
                'wallet' => $wallet,
                'message' => 'Wallet topped up successfully'
            ], 'Wallet topped up successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Withdraw from user wallet (Admin can withdraw for any user)
     */
    public function withdraw(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1',
                'description' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            // Remove buyer restriction and visibility scope for admin access
            $user = User::withoutGlobalScopes()->findOrFail($id);
            
            // Ensure wallet exists
            $walletData = $this->walletService->getBalance($user->id);
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet || $wallet->shopping_balance < $request->amount) {
                return ResponseHelper::error('Insufficient wallet balance.', 422);
            }

            DB::beginTransaction();
            try {
                // Deduct from shopping balance
                $wallet->decrement('shopping_balance', $request->amount);

                // Create transaction record
                $txId = 'WD-ADMIN-' . now()->format('YmdHis') . '-' . random_int(100000, 999999);
                Transaction::create([
                    'tx_id' => $txId,
                    'amount' => $request->amount,
                    'status' => 'completed',
                    'type' => 'withdrawl',
                    'order_id' => null,
                    'user_id' => $user->id,
                ]);

                // Log admin activity
                UserActivity::create([
                    'user_id' => $user->id,
                    'message' => "Amount withdrawn by admin: ₦{$request->amount}" . ($request->description ? " - {$request->description}" : ''),
                ]);

                DB::commit();

                return ResponseHelper::success([
                    'wallet' => $this->walletService->getBalance($user->id),
                    'message' => 'Amount withdrawn successfully'
                ], 'Amount withdrawn successfully');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user notifications (Admin can view any user's notifications)
     */
    public function getUserNotifications(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $perPage = $request->get('per_page', 20);
            $status = $request->get('status'); // 'read', 'unread', or null for all

            $query = UserNotification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($status === 'read') {
                $query->where('is_read', true);
            } elseif ($status === 'unread') {
                $query->where('is_read', false);
            }

            $notifications = $query->paginate($perPage);

            return ResponseHelper::success([
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                ],
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
                'statistics' => [
                    'total_notifications' => UserNotification::where('user_id', $user->id)->count(),
                    'unread_count' => UserNotification::where('user_id', $user->id)->where('is_read', false)->count(),
                    'read_count' => UserNotification::where('user_id', $user->id)->where('is_read', true)->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Generate user code from name
     */
    private function generateUserCode($name)
    {
        $name = explode(" ", $name);
        $code = "";
        foreach ($name as $n) {
            $code .= substr($n, 0, 1);
        }
        return strtoupper($code);
    }
}
