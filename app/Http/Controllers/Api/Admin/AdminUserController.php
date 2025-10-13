<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
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
            $query = User::with('wallet')
                ->where('role', 'buyer'); // Only buyers

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

            return ResponseHelper::success($users, 'Users retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user statistics
     */
    public function stats()
    {
        try {
            $totalUsers = User::where('role', 'buyer')->count(); // Only buyers
            $activeUsers = User::where('role', 'buyer')->where('is_active', true)->count(); // Only buyers
            $newUsers = User::where('role', 'buyer')->where('created_at', '>=', now()->subMonth())->count(); // Only buyers

            // Calculate percentage increase (mock data for now)
            $totalIncrease = 5;
            $activeIncrease = 5;
            $newIncrease = 5;

            $stats = [
                'total_users' => [
                    'value' => $totalUsers,
                    'increase' => $totalIncrease,
                    'icon' => 'users',
                    'color' => 'red'
                ],
                'active_users' => [
                    'value' => $activeUsers,
                    'increase' => $activeIncrease,
                    'icon' => 'users',
                    'color' => 'red'
                ],
                'new_users' => [
                    'value' => $newUsers,
                    'increase' => $newIncrease,
                    'icon' => 'users',
                    'color' => 'red'
                ]
            ];

            return ResponseHelper::success($stats, 'User statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
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
                        'description' => $activity->activity_type,
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
            $query = $user->orders()->with(['storeOrders.store', 'storeOrders.items.product']);

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->whereHas('storeOrders', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('storeOrders.store', function ($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%");
                })->orWhereHas('storeOrders.items.product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $orders = $query->latest()->paginate(15);

            $orders->getCollection()->transform(function ($order) {
                $storeOrder = $order->storeOrders->first();
                $product = $storeOrder ? $storeOrder->items->first() : null;
                
                return [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'store_name' => $storeOrder ? $storeOrder->store->store_name : 'Unknown Store',
                    'product_name' => $product ? $product->product->name : 'Unknown Product',
                    'price' => number_format($order->grand_total, 2),
                    'order_date' => $order->created_at->format('d-m-Y H:i:s'),
                    'status' => $storeOrder ? $storeOrder->status : 'unknown',
                    'status_color' => $this->getOrderStatusColor($storeOrder ? $storeOrder->status : 'unknown')
                ];
            });

            return ResponseHelper::success($orders, 'User orders retrieved successfully');
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
            $query = $user->orders()->with(['storeOrders.store', 'storeOrders.items.product']);

            $status = $request->get('status', 'all');
            $search = $request->get('search', '');

            if ($status !== 'all') {
                $query->whereHas('storeOrders', function ($q) use ($status) {
                    $q->where('status', $status);
                });
            }

            if ($search) {
                $query->whereHas('storeOrders.store', function ($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%");
                })->orWhereHas('storeOrders.items.product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $orders = $query->latest()->get()->map(function ($order) {
                $storeOrder = $order->storeOrders->first();
                $product = $storeOrder ? $storeOrder->items->first() : null;
                
                return [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'store_name' => $storeOrder ? $storeOrder->store->store_name : 'Unknown Store',
                    'product_name' => $product ? $product->product->name : 'Unknown Product',
                    'price' => number_format($order->grand_total, 2),
                    'order_date' => $order->created_at->format('d-m-Y H:i:s'),
                    'status' => $storeOrder ? $storeOrder->status : 'unknown',
                    'status_color' => $this->getOrderStatusColor($storeOrder ? $storeOrder->status : 'unknown')
                ];
            });

            return ResponseHelper::success($orders, 'Filtered orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on user orders
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
                \App\Models\StoreOrder::whereIn('order_id', $orderIds)->update(['status' => $newStatus]);
                $message = "Orders status updated to {$newStatus}";
            } else {
                // Delete orders
                \App\Models\Order::whereIn('id', $orderIds)->delete();
                $message = "Orders deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get complete order details (following same pattern as existing order details)
     */
    public function orderDetails($id, $orderId)
    {
        try {
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            $order = $user->orders()->findOrFail($orderId);
            
            // Load complete order details following the same pattern as Buyer OrderService
            $completeOrder = $order->load([
                'storeOrders.store',
                'storeOrders.items.product.images',
                'storeOrders.items.variant',
                'storeOrders.orderTracking',
                'deliveryAddress',
                'storeOrders.chat'
            ]);

            return ResponseHelper::success($completeOrder, 'Complete order details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $id, $orderId)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:order_placed,out_for_delivery,delivered,completed,disputed,uncompleted'
            ]);

            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            $order = $user->orders()->findOrFail($orderId);
            
            // Update store order status
            $storeOrder = $order->storeOrders->first();
            if ($storeOrder) {
                $storeOrder->update(['status' => $request->status]);
            }

            return ResponseHelper::success([
                'order_id' => $order->id,
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
     * Get user chats
     */
    public function userChats(Request $request, $id)
    {
        try {
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
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
                    $query->where('is_dispute', true);
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
                    'is_dispute' => $chat->is_dispute ?? false,
                    'chat_date' => $chat->created_at->format('d-m-Y/h:iA'),
                    'unread_count' => $chat->messages()->where('is_read', false)->count()
                ];
            });

            return ResponseHelper::success($chats, 'User chats retrieved successfully');
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
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
                    $query->where('is_dispute', true);
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
                    'is_dispute' => $chat->is_dispute ?? false,
                    'chat_date' => $chat->created_at->format('d-m-Y/h:iA'),
                    'unread_count' => $chat->messages()->where('is_read', false)->count()
                ];
            });

            return ResponseHelper::success($chats, 'Filtered chats retrieved successfully');
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
                \App\Models\Chat::whereIn('id', $chatIds)->update(['is_dispute' => true]);
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            $chat = $user->chats()->with([
                'store',
                'messages' => function($query) {
                    $query->latest();
                },
                'order.storeOrders.items.product'
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
                'order' => $chat->order ? [
                    'id' => $chat->order->id,
                    'order_no' => $chat->order->order_no,
                    'status' => $chat->order->storeOrders->first()->status ?? 'unknown',
                    'total_amount' => number_format($chat->order->grand_total, 2),
                    'items' => $chat->order->storeOrders->first() ? $chat->order->storeOrders->first()->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product->name,
                            'quantity' => $item->qty,
                            'price' => number_format($item->price, 2),
                            'total' => number_format($item->price * $item->qty, 2),
                            'product_image' => $item->product->images->first() ? 
                                asset('storage/' . $item->product->images->first()->path) : null
                        ];
                    }) : []
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
                'is_dispute' => $chat->is_dispute ?? false,
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

            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            $chat = $user->chats()->findOrFail($chatId);

            $message = \App\Models\ChatMessage::create([
                'chat_id' => $chat->id,
                'message' => $request->message,
                'sender_type' => 'admin', // Admin sending message
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
            $user = User::where('role', 'buyer')->with(['wallet', 'orders', 'transactions'])
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
            $query = Transaction::where('user_id', $id);

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Type filter
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // Date filter
            if ($request->has('date') && $request->date !== 'all') {
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

            // Get summary stats
            $allTransactions = Transaction::where('user_id', $id)->count();
            $pendingTransactions = Transaction::where('user_id', $id)->where('status', 'pending')->count();
            $successfulTransactions = Transaction::where('user_id', $id)->where('status', 'successful')->count();
            $failedTransactions = Transaction::where('user_id', $id)->where('status', 'failed')->count();

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
                    'increase' => 10, // Mock data
                    'color' => 'red'
                ],
                'pending_transactions' => [
                    'count' => $pendingTransactions,
                    'increase' => 10, // Mock data
                    'color' => 'red'
                ],
                'successful_transactions' => [
                    'count' => $successfulTransactions,
                    'increase' => 10, // Mock data
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
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

            if ($date !== 'all') {
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
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
                'channel' => $transaction->payment_method ?? 'Flutterwave',
                'description' => $transaction->description ?? 'Transaction',
                'reference' => $transaction->reference ?? $transaction->tx_id,
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
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
                    $query->whereHas('saves', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                }
            }

            // Date filter
            if ($request->has('date') && $request->date !== 'all') {
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

            $posts = $query->with(['user', 'media', 'likes', 'comments', 'saves'])
                ->latest()
                ->paginate(15);

            // Get summary stats
            $leadPosts = \App\Models\Post::where('user_id', $id)->count();
            $comments = \App\Models\PostComment::where('user_id', $id)->count();
            $savedPosts = \App\Models\PostShare::where('user_id', $id)->count();

            $posts->getCollection()->transform(function ($post) {
                return [
                    'id' => $post->id,
                    'store_name' => $post->user->full_name ?? 'Unknown User',
                    'type' => $this->getPostActivityType($post),
                    'post' => [
                        'id' => $post->id,
                        'content' => $post->content,
                        'title' => $post->title,
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
                        'shares_count' => $post->saves->count()
                    ],
                    'date' => $post->created_at->format('d-m-Y / h:i A'),
                    'created_at' => $post->created_at->format('d-m-Y H:i:s')
                ];
            });

            $summaryStats = [
                'lead_posts' => [
                    'count' => $leadPosts,
                    'increase' => 10, // Mock data
                    'color' => 'red'
                ],
                'comments' => [
                    'count' => $comments,
                    'increase' => 2, // Mock data
                    'color' => 'red'
                ],
                'saved_posts' => [
                    'count' => $savedPosts,
                    'increase' => 0, // Mock data
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            
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
                    $query->whereHas('saves', function ($q) use ($id) {
                        $q->where('user_id', $id);
                    });
                }
            }

            if ($date !== 'all') {
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

            $posts = $query->with(['user', 'media', 'likes', 'comments', 'saves'])
                ->latest()
                ->get()
                ->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'store_name' => $post->user->full_name ?? 'Unknown User',
                        'type' => $this->getPostActivityType($post),
                        'post' => [
                            'id' => $post->id,
                            'content' => $post->content,
                            'title' => $post->title,
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
                            'shares_count' => $post->saves->count()
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
                \App\Models\Post::whereIn('id', $postIds)->update(['is_approved' => true]);
                $message = "Posts approved successfully";
            } else {
                \App\Models\Post::whereIn('id', $postIds)->update(['is_approved' => false]);
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            $post = \App\Models\Post::with([
                'user',
                'media',
                'likes',
                'comments' => function($query) {
                    $query->with('user')->latest();
                },
                'saves'
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
                    'title' => $post->title,
                    'description' => $post->content,
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
                    'shares' => $post->saves->count()
                ],
                'comments' => $post->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->full_name,
                            'profile_image' => $comment->user->profile_picture ? asset('storage/' . $comment->user->profile_picture) : null
                        ],
                        'content' => $comment->content,
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
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
                    'content' => $comment->content,
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
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
                'role' => 'nullable|in:buyer,seller',
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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers

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
            $user = User::where('role', 'buyer')->findOrFail($id); // Only buyers
            $user->delete();

            return ResponseHelper::success(null, 'User deleted successfully');
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
