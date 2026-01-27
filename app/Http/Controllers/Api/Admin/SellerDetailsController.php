<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\Store;
use App\Models\Wallet;
use App\Models\Escrow;
use App\Models\UserActivity;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\StoreOrder;
use App\Models\Chat;
use App\Models\Post;
use App\Models\Product;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellerDetailsController extends Controller
{
    /**
     * Get comprehensive seller details
     */
    public function getSellerDetails($id)
    {
        try {
            $user = User::where('role', 'seller')->with([
                'stores',
                'wallet',
                'stores.businessDetails',
                'stores.addresses',
                'stores.deliveryPricing',
                'stores.socialLinks',
                'stores.categories'
            ])->findOrFail($id);

            // Check raw store visibility (ignore out-of-scope sellers)
            $rawStore = Store::withoutGlobalScopes()->where('user_id', $user->id)->first();

            if ($rawStore && (int) $rawStore->visibility === 0) {
                // Seller/store is marked as out of scope (visibility = 0)
                return ResponseHelper::error('Seller is out of scope', 404);
            }

            $primaryStore = $user->store;
            
            // Get wallet balances
            $wallet = $user->wallet;
            $escrowBalance = Escrow::where('user_id', $user->id)->sum('amount');

            // Get recent activities
            $recentActivities = UserActivity::where('user_id', $user->id)
                ->latest()
                ->limit(10)
                ->get();

            // Get store statistics
            $storeStats = $this->getStoreStatistics($user->id);

            $sellerDetails = [
                'user_info' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->user_name ?? $user->user_code ?? 'N/A',
                    'user_name' => $user->user_name ?? $user->user_code ?? 'N/A',
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                    'is_verified' => $user->is_active,
                    'is_active' => $user->is_active,
                    'account_creation' => $user->created_at ? $user->created_at->format('d/m/y - H:i A') : 'N/A',
                    'created_at' => $user->created_at ? $user->created_at->format('d/m/y - H:i A') : 'N/A',
                    'last_login' => $user->last_seen_at ? $user->last_seen_at->format('d/m/y - H:i A') : ($user->updated_at ? $user->updated_at->format('d/m/y - H:i A') : 'N/A'),
                    'location' => $primaryStore ? $primaryStore->store_location : null
                ],
                'store_info' => $primaryStore ? [
                    'id' => $primaryStore->id,
                    'store_name' => $primaryStore->store_name,
                    'store_email' => $primaryStore->store_email,
                    'store_phone' => $primaryStore->store_phone,
                    'store_location' => $primaryStore->store_location,
                    'profile_image' => $primaryStore->profile_image ? asset('storage/' . $primaryStore->profile_image) : null,
                    'banner_image' => $primaryStore->banner_image ? asset('storage/' . $primaryStore->banner_image) : null,
                    'theme_color' => $primaryStore->theme_color,
                    'status' => $primaryStore->status,
                    'onboarding_status' => $primaryStore->onboarding_status,
                    'business_details' => $primaryStore->businessDetails,
                    'addresses' => $primaryStore->addresses,
                    'delivery_pricing' => $primaryStore->deliveryPricing,
                    'social_links' => $primaryStore->socialLinks,
                    'categories' => $primaryStore->categories
                ] : null,
                'financial_info' => [
                    'store_wallet_balance' => $wallet ? [
                        'amount' => $wallet->shopping_balance,
                        'formatted' => 'N' . number_format($wallet->shopping_balance, 0)
                    ] : ['amount' => 0, 'formatted' => 'N0'],
                    'escrow_wallet_balance' => [
                        'amount' => $escrowBalance,
                        'formatted' => 'N' . number_format($escrowBalance, 0)
                    ],
                    'reward_balance' => $wallet ? [
                        'amount' => $wallet->reward_balance,
                        'formatted' => 'N' . number_format($wallet->reward_balance, 0)
                    ] : ['amount' => 0, 'formatted' => 'N0'],
                    'referral_balance' => $wallet ? [
                        'amount' => $wallet->referral_balance,
                        'formatted' => 'N' . number_format($wallet->referral_balance, 0)
                    ] : ['amount' => 0, 'formatted' => 'N0'],
                    'loyalty_points' => $wallet ? $wallet->loyality_points : 0
                ],
                'statistics' => $storeStats,
                'recent_activities' => $recentActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'activity' => $activity->message,
                        'created_at' => $activity->created_at->format('d/m/y - H:i A')
                    ];
                })
            ];

            return ResponseHelper::success($sellerDetails, 'Seller details retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller orders
     */
    public function getSellerOrders(Request $request, $id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            // Treat each StoreOrder as a separate order for seller context
            $query = StoreOrder::with([
                'order.user',
                'store',
                'items.product.images',
                'items.variant',
                'orderTracking'
            ])->where('store_id', $store->id);

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $orders = $query->latest()->paginate(15);

            $orders->getCollection()->transform(function ($storeOrder) {
                $firstItem = $storeOrder->items->first();
                return [
                    'id' => $storeOrder->id,
                    'order_no' => $storeOrder->order->order_no,
                    'customer_name' => $storeOrder->order->user->full_name,
                    'customer_email' => $storeOrder->order->user->email,
                    'store_name' => $storeOrder->store->store_name,
                    'subtotal' => 'N' . number_format($storeOrder->items_subtotal, 0),
                    'delivery_fee' => 'N' . number_format($storeOrder->shipping_fee, 0),
                    'discount' => 'N' . number_format($storeOrder->discount, 0),
                    'total' => 'N' . number_format($storeOrder->subtotal_with_shipping, 0),
                    'status' => ucfirst($storeOrder->status),
                    'status_color' => $this->getOrderStatusColor($storeOrder->status),
                    'items_count' => $storeOrder->items->count(),
                    'created_at' => $storeOrder->created_at->format('d-m-Y H:i:s'),
                    'items' => $storeOrder->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product->name ?? 'Unknown Product',
                            'quantity' => $item->qty ?? $item->quantity,
                            'price' => 'N' . number_format($item->price, 0),
                            'total' => 'N' . number_format(($item->price * ($item->qty ?? $item->quantity)), 0)
                        ];
                    }),
                    'tracking' => $storeOrder->orderTracking->isNotEmpty() ? [
                        'status' => $storeOrder->orderTracking->first()->status,
                        'updated_at' => $storeOrder->orderTracking->first()->updated_at->format('d-m-Y H:i:s')
                    ] : null,
                ];
            });

            // Build summary statistics for cards
            $totalOrders = StoreOrder::where('store_id', $store->id)->count();
            $pendingOrders = StoreOrder::where('store_id', $store->id)
                ->whereIn('status', ['pending','processing','out_for_delivery'])
                ->count();
            $completedOrders = StoreOrder::where('store_id', $store->id)
                ->where('status', 'delivered')
                ->count();

            $summaryStats = [
                'total_orders' => [
                    'value' => $totalOrders,
                    'increase' => 5,
                    'icon' => 'shopping-cart',
                    'color' => 'red',
                    'label' => 'Total Orders'
                ],
                'pending_orders' => [
                    'value' => $pendingOrders,
                    'increase' => 5,
                    'icon' => 'shopping-cart',
                    'color' => 'red',
                    'label' => 'Pending Orders'
                ],
                'completed_orders' => [
                    'value' => $completedOrders,
                    'increase' => 5,
                    'icon' => 'shopping-cart',
                    'color' => 'red',
                    'label' => 'Completed Orders'
                ]
            ];

            return ResponseHelper::success([
                'orders' => $orders,
                'statistics' => $summaryStats
            ], 'Seller orders retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller chats
     */
    public function getSellerChats(Request $request, $id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Chat::where('store_id', $store->id)
                ->with(['user','store', 'messages' => function ($q) {
                    $q->latest()->limit(1);
                }]);

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $chats = $query->latest()->paginate(15);

            // Build chat statistics for cards
            $totalChats = Chat::where('store_id', $store->id)->count();
            $unreadChats = Chat::where('store_id', $store->id)
                ->whereHas('messages', function($q) {
                    $q->where('is_read', false);
                })->count();
            $disputeChats = Chat::where('store_id', $store->id)
                ->whereHas('dispute')
                ->count();

            $chatStats = [
                'total_chats' => [
                    'value' => $totalChats,
                    'increase' => 5,
                    'icon' => 'message-circle',
                    'color' => 'red',
                    'label' => 'Total Chats'
                ],
                'unread_chats' => [
                    'value' => $unreadChats,
                    'increase' => 5,
                    'icon' => 'message-circle',
                    'color' => 'red',
                    'label' => 'Unread Chats'
                ],
                'dispute_chats' => [
                    'value' => $disputeChats,
                    'increase' => 5,
                    'icon' => 'message-circle',
                    'color' => 'red',
                    'label' => 'Dispute'
                ]
            ];

            $chats->getCollection()->transform(function ($chat) {
                $lastMessage = $chat->messages->first();
                return [
                    'id' => $chat->id,
                    'customer_name' => $chat->user->full_name,
                    'customer_email' => $chat->user->email,
                    'store_name'=>$chat->store->store_name,
                    'last_message' => $lastMessage ? [
                        'message' => $lastMessage->message,
                        'sender_type' => $lastMessage->sender_type,
                        'is_read' => $lastMessage->is_read,
                        'created_at' => $lastMessage->created_at->format('d-m-Y H:i:s')
                    ] : null,
                    'unread_count' => $chat->messages()->where('is_read', false)->count(),
                    'created_at' => $chat->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $chat->updated_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success([
                'chats' => $chats,
                'statistics' => $chatStats
            ], 'Seller chats retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller transactions
     */
    public function getSellerTransactions(Request $request, $id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);

            $query = Transaction::where('user_id', $id)->with(['order']);

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Filter by type
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $transactions = $query->latest()->paginate(15);

            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => 'N' . number_format($transaction->amount, 0),
                    'type' => ucfirst($transaction->type),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'payment_method' => 'Unknown', // Transaction model doesn't have payment_method field
                    'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                    'order' => $transaction->order ? [
                        'id' => $transaction->order->id,
                        'order_no' => $transaction->order->order_no
                    ] : null,
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($transactions, 'Seller transactions retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller social feed (posts)
     */
    public function getSellerSocialFeed(Request $request, $id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);

            $query = Post::where('user_id', $id)
                ->with(['user', 'media', 'likes', 'comments.user']);

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $posts = $query->latest()->paginate(15);

            $posts->getCollection()->transform(function ($post) {
                return [
                    'id' => $post->id,
                    'content' => $post->body,
                    'media' => $post->media->map(function ($media) {
                        return [
                            'id' => $media->id,
                            'type' => $media->type,
                            'path' => $media->path,
                            'url' => $media->path ? asset('storage/' . $media->path) : null,
                            'position' => $media->position
                        ];
                    }),
                    'likes_count' => $post->likes->count(),
                    'comments_count' => $post->comments->count(),
                    'shares_count' => $post->shares_count ?? 0,
                    'created_at' => $post->created_at->format('d-m-Y H:i:s'),
                    'user' => [
                        'id' => $post->user->id,
                        'name' => $post->user->full_name,
                        'profile_picture' => $post->user->profile_picture ? asset('storage/' . $post->user->profile_picture) : null
                    ]
                ];
            });

            return ResponseHelper::success($posts, 'Seller social feed retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller products
     */
    public function getSellerProducts(Request $request, $id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Product::where('store_id', $store->id)
                ->with(['images', 'category', 'variants']);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category_id') && $request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            $products = $query->latest()->paginate(15);

            $products->getCollection()->transform(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => 'N' . number_format($product->price, 0),
                    'sale_price' => $product->sale_price ? 'N' . number_format($product->sale_price, 0) : null,
                    'status' => ucfirst($product->status),
                    'status_color' => $this->getProductStatusColor($product->status),
                    'stock_quantity' => $product->stock_quantity,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->title
                    ] : null,
                    'images' => $product->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'url' => asset('storage/' . $image->url),
                            'is_main' => $image->is_main
                        ];
                    }),
                    'variants_count' => $product->variants->count(),
                    'created_at' => $product->created_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($products, 'Seller products retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller announcements
     */
    public function getSellerAnnouncements(Request $request, $id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Announcement::where('store_id', $store->id);

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $announcements = $query->latest()->paginate(15);

            $announcements->getCollection()->transform(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'message' => $announcement->message,
                    'impressions' => $announcement->impressions,
                    'created_at' => $announcement->created_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($announcements, 'Seller announcements retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller user activities
     */
    public function getSellerActivities(Request $request, $id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);

            $query = UserActivity::where('user_id', $id);

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $activities = $query->latest()->paginate(15);

            $activities->getCollection()->transform(function ($activity) {
                return [
                    'id' => $activity->id,
                    'activity' => $activity->activity,
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                    'created_at' => $activity->created_at->format('d/m/y - H:i A')
                ];
            });

            return ResponseHelper::success($activities, 'Seller activities retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update seller wallet (Topup/Withdraw)
     */
    public function updateSellerWallet(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:topup,withdraw',
                'amount' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($id);
            $wallet = $user->wallet;

            if (!$wallet) {
                return ResponseHelper::error('Wallet not found for this seller', 404);
            }

            if ($request->action === 'topup') {
                $wallet->shopping_balance += $request->amount;
                $message = 'Wallet topped up successfully';
            } else {
                if ($wallet->shopping_balance < $request->amount) {
                    return ResponseHelper::error('Insufficient balance for withdrawal', 400);
                }
                $wallet->shopping_balance -= $request->amount;
                $message = 'Amount withdrawn successfully';
            }

            $wallet->save();

            // Log activity
            UserActivity::create([
                'user_id' => $user->id,
                'message' => $request->action === 'topup' ? 'Wallet topped up by admin' : 'Amount withdrawn by admin',
            ]);

            return ResponseHelper::success([
                'wallet_balance' => [
                    'amount' => $wallet->shopping_balance,
                    'formatted' => 'N' . number_format($wallet->shopping_balance, 0)
                ]
            ], $message);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Block/Unblock seller
     */
    public function toggleSellerBlock(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:block,unblock'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($id);
            
            if ($request->action === 'block') {
                $user->update(['is_active' => false]);
                $message = 'Seller blocked successfully';
            } else {
                $user->update(['is_active' => true]);
                $message = 'Seller unblocked successfully';
            }

            // Log activity
            UserActivity::create([
                'user_id' => $user->id,
                'message' => $request->action === 'block' ? 'Account blocked by admin' : 'Account unblocked by admin',
            ]);

            return ResponseHelper::success([
                'user_id' => $user->id,
                'is_active' => $user->is_active
            ], $message);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete seller
     */
    public function deleteSeller($id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);
            $user->delete();

            return ResponseHelper::success(null, 'Seller deleted successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get store statistics
     */
    private function getStoreStatistics($userId)
    {
        $store = Store::where('user_id', $userId)->first();
        
        if (!$store) {
            return [
                'total_products' => 0,
                'total_orders' => 0,
                'total_revenue' => 0,
                'total_customers' => 0,
                'average_rating' => 0
            ];
        }

        $totalProducts = Product::where('store_id', $store->id)->count();
        $totalOrders = Order::whereHas('storeOrders', function ($q) use ($store) {
            $q->where('store_id', $store->id);
        })->count();
        $totalRevenue = Order::whereHas('storeOrders', function ($q) use ($store) {
            $q->where('store_id', $store->id);
        })->sum('grand_total');
        $totalCustomers = Order::whereHas('storeOrders', function ($q) use ($store) {
            $q->where('store_id', $store->id);
        })->distinct('user_id')->count('user_id');

        return [
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'total_revenue' => [
                'amount' => $totalRevenue,
                'formatted' => 'N' . number_format($totalRevenue, 0)
            ],
            'total_customers' => $totalCustomers,
            'average_rating' => $store->average_rating ?? 0
        ];
    }

    /**
     * Get order status color
     */
    private function getOrderStatusColor($status)
    {
        $colors = [
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray'
        ];

        return $colors[$status] ?? 'gray';
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
     * Get product status color
     */
    private function getProductStatusColor($status)
    {
        $colors = [
            'active' => 'green',
            'inactive' => 'red',
            'draft' => 'yellow',
            'archived' => 'gray'
        ];

        return $colors[$status] ?? 'gray';
    }
}
