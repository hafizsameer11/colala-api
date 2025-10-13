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

            $primaryStore = $user->stores->first();
            
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
                    'username' => $user->user_code,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                    'is_verified' => $user->is_active,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at->format('d/m/y - H:i A'),
                    'last_login' => $user->updated_at->format('d/m/y - H:i A'),
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
                        'activity' => $activity->activity,
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
            $store = $user->stores->first();

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Order::whereHas('storeOrders', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->with(['user', 'storeOrders.items.product']);

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

            $orders->getCollection()->transform(function ($order) {
                return [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'customer_name' => $order->user->full_name,
                    'customer_email' => $order->user->email,
                    'total_amount' => 'N' . number_format($order->grand_total, 0),
                    'status' => ucfirst($order->status),
                    'status_color' => $this->getOrderStatusColor($order->status),
                    'items_count' => $order->storeOrders->sum(function ($storeOrder) {
                        return $storeOrder->items->count();
                    }),
                    'created_at' => $order->created_at->format('d-m-Y H:i:s'),
                    'store_orders' => $order->storeOrders->map(function ($storeOrder) {
                        return [
                            'id' => $storeOrder->id,
                            'store_name' => $storeOrder->store->store_name,
                            'subtotal' => 'N' . number_format($storeOrder->subtotal, 0),
                            'delivery_fee' => 'N' . number_format($storeOrder->delivery_fee, 0),
                            'total' => 'N' . number_format($storeOrder->total, 0),
                            'status' => ucfirst($storeOrder->status),
                            'items' => $storeOrder->items->map(function ($item) {
                                return [
                                    'id' => $item->id,
                                    'product_name' => $item->product->name ?? 'Unknown Product',
                                    'quantity' => $item->quantity,
                                    'price' => 'N' . number_format($item->price, 0),
                                    'total' => 'N' . number_format($item->total, 0)
                                ];
                            })
                        ];
                    })
                ];
            });

            return ResponseHelper::success($orders, 'Seller orders retrieved successfully');

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
            $store = $user->stores->first();

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Chat::where('store_id', $store->id)
                ->with(['user', 'messages' => function ($q) {
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

            $chats->getCollection()->transform(function ($chat) {
                $lastMessage = $chat->messages->first();
                return [
                    'id' => $chat->id,
                    'customer_name' => $chat->user->full_name,
                    'customer_email' => $chat->user->email,
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

            return ResponseHelper::success($chats, 'Seller chats retrieved successfully');

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
                    'payment_method' => $transaction->payment_method ?? 'Unknown',
                    'reference' => $transaction->reference ?? $transaction->tx_id,
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
                    'content' => $post->content,
                    'media' => $post->media->map(function ($media) {
                        return [
                            'id' => $media->id,
                            'type' => $media->type,
                            'url' => asset('storage/' . $media->url)
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
            $store = $user->stores->first();

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
                            'is_primary' => $image->is_primary
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
            $store = $user->stores->first();

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
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'type' => ucfirst($announcement->type),
                    'is_active' => $announcement->is_active,
                    'start_date' => $announcement->start_date ? $announcement->start_date->format('d-m-Y') : null,
                    'end_date' => $announcement->end_date ? $announcement->end_date->format('d-m-Y') : null,
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
                'activity' => $request->action === 'topup' ? 'Wallet topped up by admin' : 'Amount withdrawn by admin',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
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
                'activity' => $request->action === 'block' ? 'Account blocked by admin' : 'Account unblocked by admin',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
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
