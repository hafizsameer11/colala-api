<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\Order;
use App\Models\OrderTracking;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltySetting;
use App\Models\Wallet;
use App\Models\StoreReferralEarning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellerOrderController extends Controller
{
    /**
     * Get all orders for a specific seller
     */
    public function getSellerOrders(Request $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = StoreOrder::with([
                'order.user',
                'store',
                'items.product.images',
                'orderTracking',
                'chat',
                'order.deliveryAddress'
            ])->where('store_id', $store->id);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Search by product name or customer name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('order.user', function ($userQuery) use ($search) {
                        $userQuery->where('full_name', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('items.product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
                });
            }

            $orders = $query->latest()->paginate(20);

            // Get summary statistics
            $totalOrders = StoreOrder::where('store_id', $store->id)->count();
            $pendingOrders = StoreOrder::where('store_id', $store->id)->where('status', 'pending')->count();
            $completedOrders = StoreOrder::where('store_id', $store->id)->where('status', 'delivered')->count();

            $orders->getCollection()->transform(function ($storeOrder) {
                return [
                    'id' => $storeOrder->id,
                    'order_id' => $storeOrder->order_id,
                    'store_name' => $storeOrder->store->store_name,
                    'customer_name' => $storeOrder->order->user->full_name,
                    'customer_email' => $storeOrder->order->user->email,
                    'customer_phone' => $storeOrder->order->user->phone,
                    'product_name' => $storeOrder->items->first()?->product?->name ?? 'Multiple Products',
                    'product_count' => $storeOrder->items->count(),
                    'items_subtotal' => 'N' . number_format($storeOrder->items_subtotal, 0),
                    'shipping_fee' => 'N' . number_format($storeOrder->shipping_fee, 0),
                    'discount' => 'N' . number_format($storeOrder->discount, 0),
                    'total' => 'N' . number_format($storeOrder->subtotal_with_shipping, 0),
                    'status' => ucfirst(str_replace('_', ' ', $storeOrder->status)),
                    'status_color' => $this->getOrderStatusColor($storeOrder->status),
                    'order_date' => $storeOrder->created_at->format('d-m-Y/H:iA'),
                    'delivery_address' => $storeOrder->order->deliveryAddress ? [
                        'full_address' => $storeOrder->order->deliveryAddress->full_address,
                        'state' => $storeOrder->order->deliveryAddress->state,
                        'local_government' => $storeOrder->order->deliveryAddress->local_government,
                        'contact_name' => $storeOrder->order->deliveryAddress->contact_name,
                        'contact_phone' => $storeOrder->order->deliveryAddress->contact_phone
                    ] : null,
                    'tracking' => $storeOrder->orderTracking && $storeOrder->orderTracking->isNotEmpty() ? [
                        'delivery_code' => $storeOrder->orderTracking->first()->delivery_code,
                        'status' => $storeOrder->orderTracking->first()->status,
                        'updated_at' => $storeOrder->orderTracking->first()->updated_at->format('d-m-Y H:i:s')
                    ] : null,
                    'chat_available' => $storeOrder->chat ? true : false,
                    'chat_id' => $storeOrder->chat?->id
                ];
            });

            return ResponseHelper::success([
                'orders' => $orders,
                'summary_stats' => [
                    'total_orders' => [
                        'count' => $totalOrders,
                        'increase' => 6, // Mock data
                        'color' => 'red'
                    ],
                    'pending_orders' => [
                        'count' => $pendingOrders,
                        'increase' => 6, // Mock data
                        'color' => 'red'
                    ],
                    'completed_orders' => [
                        'count' => $completedOrders,
                        'increase' => 6, // Mock data
                        'color' => 'red'
                    ]
                ]
            ], 'Seller orders retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed order information for a specific store order
     */
    public function getOrderDetails($userId, $storeOrderId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $storeOrder = StoreOrder::with([
                'order.user',
                'store',
                'items.product.images',
                'items.product.variants',
                'orderTracking',
                'chat.messages',
                'order.deliveryAddress',
                'escrows'
            ])->where('store_id', $store->id)
              ->findOrFail($storeOrderId);

            // Build order details
            $orderDetails = [
                'id' => $storeOrder->id,
                'order_no' => $storeOrder->order->order_no,
                'status' => $storeOrder->status,
                'status_color' => $this->getOrderStatusColor($storeOrder->status),
                'store' => [
                    'id' => $storeOrder->store->id,
                    'name' => $storeOrder->store->store_name,
                    'email' => $storeOrder->store->store_email,
                    'phone' => $storeOrder->store->store_phone,
                    'location' => $storeOrder->store->addresses->first()?->full_address
                ],
                'customer' => [
                    'id' => $storeOrder->order->user->id,
                    'name' => $storeOrder->order->user->full_name,
                    'email' => $storeOrder->order->user->email,
                    'phone' => $storeOrder->order->user->phone,
                    'profile_picture' => $storeOrder->order->user->profile_picture ? asset('storage/' . $storeOrder->order->user->profile_picture) : null
                ],
                'delivery_address' => $storeOrder->order->deliveryAddress ? [
                    'id' => $storeOrder->order->deliveryAddress->id,
                    'full_address' => $storeOrder->order->deliveryAddress->full_address,
                    'state' => $storeOrder->order->deliveryAddress->state,
                    'local_government' => $storeOrder->order->deliveryAddress->local_government,
                    'contact_name' => $storeOrder->order->deliveryAddress->contact_name,
                    'contact_phone' => $storeOrder->order->deliveryAddress->contact_phone
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
                                    'path' => asset('storage/' . ($image->path ?? $image->url)),
                                    'is_main' => $image->is_main,
                                    'type' => $image->type ?? null
                                ];
                            }),
                            'variants' => $item->product->variants->map(function ($variant) {
                                return [
                                    'id' => $variant->id,
                                    'name' => $variant->name,
                                    'price' => $variant->price ?? ($variant->price_adjustment ?? null),
                                    'stock' => $variant->stock ?? null,
                                    'is_active' => $variant->is_active ?? null
                                ];
                            }),
                            'store' => [
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
                            ],
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
                                    'path' => asset('storage/' . ($image->path ?? $image->url)),
                                    'is_main' => $image->is_main
                                ];
                            })
                        ],
                        'variant' => $item->variant ? [
                            'id' => $item->variant->id,
                            'name' => $item->variant->name,
                            'price' => $item->variant->price
                        ] : null,
                        'quantity' => $item->qty ?? $item->quantity,
                        'price' => $item->price,
                        'total' => $item->price * ($item->qty ?? $item->quantity)
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
                    'is_dispute' => $storeOrder->chat->dispute ? true : false,
                    'last_message' => $storeOrder->chat->messages()->latest()->first()?->message
                ] : null,
                'created_at' => $storeOrder->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $storeOrder->updated_at->format('d-m-Y H:i:s')
            ];

            // Attach statistics for the seller's store (for cards)
            $totalOrders = StoreOrder::where('store_id', $store->id)->count();
            $pendingOrders = StoreOrder::where('store_id', $store->id)
                ->whereIn('status', ['pending','processing','out_for_delivery'])
                ->count();
            $completedOrders = StoreOrder::where('store_id', $store->id)
                ->where('status', 'delivered')
                ->count();

            $orderDetails['statistics'] = [
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

            return ResponseHelper::success($orderDetails, 'Complete order details retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $userId, $storeOrderId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,processing,out_for_delivery,delivered,cancelled,refunded'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $storeOrder = StoreOrder::where('store_id', $store->id)->findOrFail($storeOrderId);
            $storeOrder->update(['status' => $request->status]);

            // Update order tracking if exists
            if ($storeOrder->orderTracking) {
                $storeOrder->orderTracking->update(['status' => $request->status]);
            }

            return ResponseHelper::success([
                'order_id' => $storeOrder->id,
                'status' => ucfirst(str_replace('_', ' ', $storeOrder->status)),
                'status_color' => $this->getOrderStatusColor($storeOrder->status),
                'updated_at' => $storeOrder->updated_at->format('d-m-Y H:i:s')
            ], 'Order status updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark order as out for delivery
     */
    public function markOutForDelivery(Request $request, $userId, $storeOrderId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $storeOrder = StoreOrder::where('store_id', $store->id)->findOrFail($storeOrderId);
            $storeOrder->update(['status' => 'out_for_delivery']);

            // Create or update order tracking
            $orderTracking = OrderTracking::where('store_order_id', $storeOrderId)->first();
            if (!$orderTracking) {
                $deliveryCode = random_int(100000, 999999);
                $orderTracking = OrderTracking::create([
                    'store_order_id' => $storeOrderId,
                    'delivery_code' => $deliveryCode,
                    'status' => 'out_for_delivery'
                ]);
            } else {
                $orderTracking->update(['status' => 'out_for_delivery']);
            }

            return ResponseHelper::success([
                'order_id' => $storeOrder->id,
                'delivery_code' => $orderTracking->delivery_code,
                'status' => 'Out for delivery',
                'status_color' => 'purple',
                'updated_at' => $storeOrder->updated_at->format('d-m-Y H:i:s')
            ], 'Order marked as out for delivery successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Verify delivery code and mark as delivered
     */
    public function verifyDeliveryCode(Request $request, $userId, $storeOrderId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'delivery_code' => 'required|string|size:6'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $storeOrder = StoreOrder::where('store_id', $store->id)->findOrFail($storeOrderId);
            $orderTracking = OrderTracking::where('store_order_id', $storeOrderId)->first();

            if (!$orderTracking || $orderTracking->delivery_code !== $request->delivery_code) {
                return ResponseHelper::error('Invalid delivery code', 400);
            }

            $wasDelivered = $storeOrder->status === 'delivered';
            $storeOrder->update(['status' => 'delivered']);
            $orderTracking->update(['status' => 'delivered']);

            // Award loyalty points on first-time delivery confirmation
            if (!$wasDelivered) {
                $this->awardLoyaltyPoints($storeOrder);
            }

            return ResponseHelper::success([
                'order_id' => $storeOrder->id,
                'status' => 'Delivered',
                'status_color' => 'green',
                'delivery_confirmed_at' => now()->format('d-m-Y H:i:s')
            ], 'Delivery verified successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get order statistics for seller
     */
    public function getOrderStatistics($userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $totalOrders = StoreOrder::where('store_id', $store->id)->count();
            $pendingOrders = StoreOrder::where('store_id', $store->id)->where('status', 'pending')->count();
            $processingOrders = StoreOrder::where('store_id', $store->id)->where('status', 'processing')->count();
            $outForDeliveryOrders = StoreOrder::where('store_id', $store->id)->where('status', 'out_for_delivery')->count();
            $deliveredOrders = StoreOrder::where('store_id', $store->id)->where('status', 'delivered')->count();
            $cancelledOrders = StoreOrder::where('store_id', $store->id)->where('status', 'cancelled')->count();

            $totalRevenue = StoreOrder::where('store_id', $store->id)->sum('total');
            $monthlyRevenue = StoreOrder::where('store_id', $store->id)
                ->whereMonth('created_at', now()->month)
                ->sum('total');

            return ResponseHelper::success([
                'order_counts' => [
                    'total' => $totalOrders,
                    'pending' => $pendingOrders,
                    'processing' => $processingOrders,
                    'out_for_delivery' => $outForDeliveryOrders,
                    'delivered' => $deliveredOrders,
                    'cancelled' => $cancelledOrders
                ],
                'revenue' => [
                    'total' => [
                        'amount' => $totalRevenue,
                        'formatted' => 'N' . number_format($totalRevenue, 0)
                    ],
                    'monthly' => [
                        'amount' => $monthlyRevenue,
                        'formatted' => 'N' . number_format($monthlyRevenue, 0)
                    ]
                ],
                'completion_rate' => $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0
            ], 'Order statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Award loyalty points for completed order
     */
    private function awardLoyaltyPoints($storeOrder)
    {
        try {
            $setting = LoyaltySetting::where('store_id', $storeOrder->store_id)->first();
            if ($setting && $setting->enable_order_points && (int)$setting->points_per_order > 0) {
                $points = (int)$setting->points_per_order;
                LoyaltyPoint::create([
                    'user_id' => $storeOrder->order->user_id,
                    'store_id' => $storeOrder->store_id,
                    'points' => $points,
                    'source' => 'order',
                ]);

                // Update wallet loyalty points
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $storeOrder->order->user_id],
                    ['shopping_balance' => 0, 'reward_balance' => 0, 'referral_balance' => 0, 'loyality_points' => 0]
                );
                $wallet->increment('loyality_points', $points);

                // Referral bonus
                $buyer = User::find($storeOrder->order->user_id);
                $inviteCode = $buyer?->invite_code;
                if ($inviteCode) {
                    $referrer = User::where('user_code', $inviteCode)->first();
                    if ($referrer && $setting->enable_referral_points && (int)$setting->points_per_referral > 0) {
                        $refPoints = (int)$setting->points_per_referral;

                        StoreReferralEarning::create([
                            'user_id' => $referrer->id,
                            'store_id' => $storeOrder->store_id,
                            'order_id' => $storeOrder->order_id,
                            'amount' => $refPoints,
                        ]);

                        $refWallet = Wallet::firstOrCreate(
                            ['user_id' => $referrer->id],
                            ['shopping_balance' => 0, 'reward_balance' => 0, 'referral_balance' => 0, 'loyality_points' => 0]
                        );
                        $refWallet->increment('referral_balance', $refPoints);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Loyalty points error: ' . $e->getMessage());
        }
    }

    /**
     * Get order timeline
     */
    private function getOrderTimeline($storeOrder)
    {
        $timeline = [
            [
                'status' => 'Order Placed',
                'date' => $storeOrder->created_at->format('d-m-Y H:i:s'),
                'description' => 'Order was placed by customer',
                'completed' => true
            ]
        ];

        if ($storeOrder->status !== 'pending') {
            $timeline[] = [
                'status' => 'Processing',
                'date' => $storeOrder->updated_at->format('d-m-Y H:i:s'),
                'description' => 'Order is being processed',
                'completed' => in_array($storeOrder->status, ['processing', 'out_for_delivery', 'delivered'])
            ];
        }

        if (in_array($storeOrder->status, ['out_for_delivery', 'delivered'])) {
            $timeline[] = [
                'status' => 'Out for Delivery',
                'date' => $storeOrder->orderTracking?->updated_at?->format('d-m-Y H:i:s') ?? $storeOrder->updated_at->format('d-m-Y H:i:s'),
                'description' => 'Order is out for delivery',
                'completed' => $storeOrder->status === 'delivered'
            ];
        }

        if ($storeOrder->status === 'delivered') {
            $timeline[] = [
                'status' => 'Delivered',
                'date' => $storeOrder->updated_at->format('d-m-Y H:i:s'),
                'description' => 'Order has been delivered',
                'completed' => true
            ];
        }

        return $timeline;
    }

    /**
     * Get order status color
     */
    private function getOrderStatusColor($status)
    {
        $colors = [
            'pending' => 'red',
            'processing' => 'blue',
            'out_for_delivery' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray'
        ];

        return $colors[$status] ?? 'gray';
    }
}
