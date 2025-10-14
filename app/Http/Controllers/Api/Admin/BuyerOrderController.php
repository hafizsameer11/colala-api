<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Order;
use App\Models\StoreOrder;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\OrderTracking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BuyerOrderController extends Controller
{
    /**
     * Get all store orders with comprehensive details and summary stats
     */
    public function index(Request $request)
    {
        try {
            // Get store orders directly (treating them as primary orders)
            $query = StoreOrder::with([
                'order.user',
                'store.user',
                'items.product.images',
                'items.product.variants',
                'items.variant',
                'orderTracking',
                'chat'
            ])->whereHas('order.user', function ($q) {
                $q->where('role', 'buyer');
            });

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
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
                    $q->whereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('order.user', function ($userQuery) use ($search) {
                        $userQuery->where('full_name', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery->where('store_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
                });
            }

            $storeOrders = $query->latest()->paginate(15);

            // Get comprehensive summary stats
            $totalStoreOrders = StoreOrder::whereHas('order.user', function ($q) {
                $q->where('role', 'buyer');
            })->count();
            
            $pendingStoreOrders = StoreOrder::whereHas('order.user', function ($q) {
                $q->where('role', 'buyer');
            })->whereIn('status', ['pending', 'order_placed', 'processing'])->count();
            
            $completedStoreOrders = StoreOrder::whereHas('order.user', function ($q) {
                $q->where('role', 'buyer');
            })->where('status', 'completed')->count();

            $storeOrders->getCollection()->transform(function ($storeOrder) {
                $firstItem = $storeOrder->items->first();
                $product = $firstItem ? $firstItem->product : null;
                
                return [
                    'id' => $storeOrder->id,
                    'order_no' => $storeOrder->order->order_no,
                    'buyer' => [
                        'id' => $storeOrder->order->user->id,
                        'name' => $storeOrder->order->user->full_name,
                        'email' => $storeOrder->order->user->email,
                        'phone' => $storeOrder->order->user->phone,
                        'profile_picture' => $storeOrder->order->user->profile_picture ? asset('storage/' . $storeOrder->order->user->profile_picture) : null
                    ],
                    'store' => [
                        'id' => $storeOrder->store->id,
                        'name' => $storeOrder->store->store_name,
                        'email' => $storeOrder->store->store_email,
                        'phone' => $storeOrder->store->store_phone,
                        'location' => $storeOrder->store->store_location,
                        'profile_image' => $storeOrder->store->profile_image ? asset('storage/' . $storeOrder->store->profile_image) : null,
                        'seller' => [
                            'id' => $storeOrder->store->user->id,
                            'name' => $storeOrder->store->user->full_name,
                            'email' => $storeOrder->store->user->email,
                            'phone' => $storeOrder->store->user->phone
                        ]
                    ],
                    'product' => $product ? [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'price' => $product->price,
                        'discount_price' => $product->discount_price,
                        'main_image' => $product->images->where('is_main', true)->first() ? 
                            asset('storage/' . $product->images->where('is_main', true)->first()->path) : null,
                        'images' => $product->images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'path' => asset('storage/' . $image->path),
                                'is_main' => $image->is_main
                            ];
                        })
                    ] : null,
                    'order_item' => $firstItem ? [
                        'id' => $firstItem->id,
                        'quantity' => $firstItem->qty,
                        'unit_price' => $firstItem->price,
                        'total_price' => $firstItem->price * $firstItem->qty,
                        'variant' => $firstItem->variant ? [
                            'id' => $firstItem->variant->id,
                            'name' => $firstItem->variant->name,
                            'value' => $firstItem->variant->value,
                            'price' => $firstItem->variant->price
                        ] : null
                    ] : null,
                    'pricing' => [
                        'items_subtotal' => $storeOrder->items_subtotal,
                        'shipping_fee' => $storeOrder->shipping_fee,
                        'discount' => $storeOrder->discount,
                        'subtotal_with_shipping' => $storeOrder->subtotal_with_shipping
                    ],
                    'order_date' => $storeOrder->created_at->format('d-m-Y/h:iA'),
                    'status' => $this->formatOrderStatus($storeOrder->status),
                    'status_color' => $this->getOrderStatusColor($storeOrder->status),
                    'tracking' => $storeOrder->orderTracking->isNotEmpty() ? [
                        'current_status' => $storeOrder->orderTracking->first()->status,
                        'tracking_number' => $storeOrder->orderTracking->first()->tracking_number,
                        'carrier' => $storeOrder->orderTracking->first()->carrier,
                        'estimated_delivery' => $storeOrder->orderTracking->first()->estimated_delivery,
                        'last_updated' => $storeOrder->orderTracking->first()->updated_at->format('d-m-Y h:iA'),
                        'notes' => $storeOrder->orderTracking->first()->notes
                    ] : null,
                    'chat' => $storeOrder->chat ? [
                        'id' => $storeOrder->chat->id,
                        'is_dispute' => $storeOrder->chat->dispute ? true : false,
                        'last_message' => $storeOrder->chat->messages()->latest()->first()?->message
                    ] : null
                ];
            });

            $summaryStats = [
                'total_store_orders' => [
                    'count' => $totalStoreOrders,
                    'increase' => 5, // Mock data
                    'color' => 'red',
                    'icon' => 'shopping-cart',
                    'label' => 'Total StoreOrders'
                ],
                'pending_store_orders' => [
                    'count' => $pendingStoreOrders,
                    'increase' => 5, // Mock data
                    'color' => 'yellow',
                    'icon' => 'clock',
                    'label' => 'Pending StoreOrders'
                ],
                'completed_store_orders' => [
                    'count' => $completedStoreOrders,
                    'increase' => 5, // Mock data
                    'color' => 'green',
                    'icon' => 'check-circle',
                    'label' => 'Completed StoreOrders'
                ]
            ];

            return ResponseHelper::success([
                'store_orders' => $storeOrders,
                'summary_stats' => $summaryStats,
                'pagination' => [
                    'current_page' => $storeOrders->currentPage(),
                    'last_page' => $storeOrders->lastPage(),
                    'per_page' => $storeOrders->perPage(),
                    'total' => $storeOrders->total(),
                ]
            ], 'Store orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Filter buyer orders
     */
    public function filter(Request $request)
    {
        try {
            $query = Order::with(['user', 'storeOrders.store', 'storeOrders.orderTracking'])
                ->whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                });

            $status = $request->get('status', 'all');
            $date = $request->get('date', 'all');
            $search = $request->get('search', '');

            if ($status !== 'all') {
                $query->whereHas('storeOrders', function ($q) use ($status) {
                    $q->where('status', $status);
                });
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
                    $q->where('order_no', 'like', "%{$search}%")
                      ->orWhere('grand_total', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      })
                      ->orWhereHas('storeOrders.store', function ($storeQuery) use ($search) {
                          $storeQuery->where('store_name', 'like', "%{$search}%");
                      });
                });
            }

            $orders = $query->latest()->get()->map(function ($order) {
                $storeOrder = $order->storeOrders->first();
                return [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'buyer' => [
                        'id' => $order->user->id,
                        'name' => $order->user->full_name,
                        'email' => $order->user->email,
                        'phone' => $order->user->phone
                    ],
                    'store' => $storeOrder ? [
                        'id' => $storeOrder->store->id,
                        'name' => $storeOrder->store->store_name,
                        'seller' => $storeOrder->store->user->full_name ?? 'Unknown'
                    ] : null,
                    'product' => $this->getOrderProductInfo($order),
                    'price' => 'N' . number_format($order->grand_total, 0),
                    'order_date' => $order->created_at->format('d-m-Y/h:iA'),
                    'status' => $storeOrder ? $this->formatOrderStatus($storeOrder->status) : 'Unknown',
                    'status_color' => $this->getOrderStatusColor($storeOrder ? $storeOrder->status : 'unknown'),
                    'tracking' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? [
                        'current_status' => $storeOrder->orderTracking->first()->status,
                        'last_updated' => $storeOrder->orderTracking->first()->updated_at->format('d-m-Y h:iA')
                    ] : null
                ];
            });

            return ResponseHelper::success($orders, 'Filtered orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on buyer orders
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'order_ids' => 'required|array',
                'action' => 'required|string|in:update_status,mark_completed,mark_disputed,delete'
            ]);

            $orderIds = $request->order_ids;
            $action = $request->action;

            if ($action === 'update_status') {
                $request->validate(['status' => 'required|string']);
                StoreOrder::whereIn('order_id', $orderIds)->update(['status' => $request->status]);
                $message = "Order status updated successfully";
            } elseif ($action === 'mark_completed') {
                StoreOrder::whereIn('order_id', $orderIds)->update(['status' => 'completed']);
                $message = "Orders marked as completed";
            } elseif ($action === 'mark_disputed') {
                StoreOrder::whereIn('order_id', $orderIds)->update(['status' => 'disputed']);
                $message = "Orders marked as disputed";
            } else {
                Order::whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
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
     * Get detailed store order information
     */
    public function orderDetails($storeOrderId)
    {
        try {
            $storeOrder = StoreOrder::with([
                'order.user',
                'order.deliveryAddress',
                'store.user',
                'items.product.images',
                'items.product.variants',
                'items.product.reviews.user',
                'items.variant',
                'orderTracking',
                'chat.messages'
            ])->whereHas('order.user', function ($q) {
                $q->where('role', 'buyer');
            })->findOrFail($storeOrderId);
            
            $orderDetails = [
                'store_order_info' => [
                    'id' => $storeOrder->id,
                    'order_no' => $storeOrder->order->order_no,
                    'status' => $this->formatOrderStatus($storeOrder->status),
                    'status_color' => $this->getOrderStatusColor($storeOrder->status),
                    'total_amount' => 'N' . number_format($storeOrder->subtotal_with_shipping, 2),
                    'order_date' => $storeOrder->created_at->format('d-m-Y h:iA'),
                    'updated_at' => $storeOrder->updated_at->format('d-m-Y h:iA')
                ],
                'buyer_info' => [
                    'id' => $storeOrder->order->user->id,
                    'name' => $storeOrder->order->user->full_name,
                    'email' => $storeOrder->order->user->email,
                    'phone' => $storeOrder->order->user->phone,
                    'profile_picture' => $storeOrder->order->user->profile_picture ? asset('storage/' . $storeOrder->order->user->profile_picture) : null
                ],
                'store_info' => [
                    'id' => $storeOrder->store->id,
                    'name' => $storeOrder->store->store_name,
                    'email' => $storeOrder->store->store_email,
                    'phone' => $storeOrder->store->store_phone,
                    'location' => $storeOrder->store->store_location,
                    'profile_image' => $storeOrder->store->profile_image ? asset('storage/' . $storeOrder->store->profile_image) : null,
                    'banner_image' => $storeOrder->store->banner_image ? asset('storage/' . $storeOrder->store->banner_image) : null,
                    'theme_color' => $storeOrder->store->theme_color,
                    'average_rating' => $storeOrder->store->average_rating,
                    'total_sold' => $storeOrder->store->total_sold,
                    'followers_count' => $storeOrder->store->followers_count,
                    'seller' => [
                        'id' => $storeOrder->store->user->id,
                        'name' => $storeOrder->store->user->full_name,
                        'email' => $storeOrder->store->user->email,
                        'phone' => $storeOrder->store->user->phone,
                        'profile_picture' => $storeOrder->store->user->profile_picture ? asset('storage/' . $storeOrder->store->user->profile_picture) : null
                    ]
                ],
                'delivery_address' => $storeOrder->order->deliveryAddress ? [
                    'id' => $storeOrder->order->deliveryAddress->id,
                    'full_address' => $storeOrder->order->deliveryAddress->full_address,
                    'state' => $storeOrder->order->deliveryAddress->state,
                    'local_government' => $storeOrder->order->deliveryAddress->local_government,
                    'contact_name' => $storeOrder->order->deliveryAddress->contact_name,
                    'contact_phone' => $storeOrder->order->deliveryAddress->contact_phone
                ] : null,
                'order_items' => $storeOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'description' => $item->product->description,
                            'price' => $item->product->price,
                            'discount_price' => $item->product->discount_price,
                            'quantity' => $item->product->quantity,
                            'status' => $item->product->status,
                            'is_featured' => $item->product->is_featured,
                            'created_at' => $item->product->created_at->format('d-m-Y H:i:s'),
                            'images' => $item->product->images->map(function ($image) {
                                return [
                                    'id' => $image->id,
                                    'path' => asset('storage/' . $image->path),
                                    'is_main' => $image->is_main,
                                    'type' => $image->type
                                ];
                            }),
                            'variants' => $item->product->variants,
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
                        'variant' => $item->variant ? [
                            'id' => $item->variant->id,
                            'name' => $item->variant->name,
                            'value' => $item->variant->value,
                            'price' => $item->variant->price
                        ] : null,
                        'quantity' => $item->qty,
                        'unit_price' => $item->price,
                        'total_price' => $item->price * $item->qty
                    ];
                }),
                'tracking_info' => $storeOrder->orderTracking->isNotEmpty() ? [
                    'current_status' => $storeOrder->orderTracking->first()->status,
                    'tracking_number' => $storeOrder->orderTracking->first()->tracking_number,
                    'carrier' => $storeOrder->orderTracking->first()->carrier,
                    'estimated_delivery' => $storeOrder->orderTracking->first()->estimated_delivery,
                    'last_updated' => $storeOrder->orderTracking->first()->updated_at->format('d-m-Y h:iA'),
                    'notes' => $storeOrder->orderTracking->first()->notes
                ] : null,
                'pricing' => [
                    'items_subtotal' => $storeOrder->items_subtotal,
                    'shipping_fee' => $storeOrder->shipping_fee,
                    'discount' => $storeOrder->discount,
                    'subtotal_with_shipping' => $storeOrder->subtotal_with_shipping
                ],
                'payment_info' => [
                    'payment_method' => $storeOrder->order->payment_method ?? 'Unknown',
                    'payment_status' => $storeOrder->order->payment_status ?? 'Unknown',
                    'transaction_id' => $storeOrder->order->transaction_id ?? null
                ],
                'chat' => $storeOrder->chat ? [
                    'id' => $storeOrder->chat->id,
                    'is_dispute' => $storeOrder->chat->dispute ? true : false,
                    'last_message' => $storeOrder->chat->messages->first()?->message,
                    'unread_count' => $storeOrder->chat->messages()->where('is_read', false)->count()
                ] : null,
                'created_at' => $storeOrder->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $storeOrder->updated_at->format('d-m-Y H:i:s')
            ];

            return ResponseHelper::success($orderDetails, 'Store order details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $storeOrderId)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending,order_placed,processing,out_for_delivery,delivered,completed,disputed,uncompleted',
                'tracking_number' => 'nullable|string',
                'carrier' => 'nullable|string',
                'estimated_delivery' => 'nullable|date',
                'notes' => 'nullable|string'
            ]);

            $storeOrder = StoreOrder::whereHas('order.user', function ($q) {
                $q->where('role', 'buyer');
            })->findOrFail($storeOrderId);

            // Update store order status
            $storeOrder->update(['status' => $request->status]);

            // Update or create tracking info
            $trackingData = [
                'store_order_id' => $storeOrder->id,
                'status' => $request->status,
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'estimated_delivery' => $request->estimated_delivery,
                'notes' => $request->notes
            ];

            OrderTracking::updateOrCreate(
                ['store_order_id' => $storeOrder->id],
                $trackingData
            );

            return ResponseHelper::success([
                'store_order_id' => $storeOrder->id,
                'new_status' => $request->status,
                'status_color' => $this->getOrderStatusColor($request->status)
            ], 'Store order status updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get store order tracking history
     */
    public function orderTracking($storeOrderId)
    {
        try {
            $storeOrder = StoreOrder::with(['order.user', 'orderTracking'])
                ->whereHas('order.user', function ($q) {
                    $q->where('role', 'buyer');
                })->findOrFail($storeOrderId);
            
            $trackingInfo = [
                'store_order_id' => $storeOrder->id,
                'order_no' => $storeOrder->order->order_no,
                'current_status' => $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->status : 'Unknown',
                'tracking_number' => $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->tracking_number : null,
                'carrier' => $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->carrier : null,
                'estimated_delivery' => $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->estimated_delivery : null,
                'last_updated' => $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->updated_at->format('d-m-Y h:iA') : null,
                'notes' => $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->notes : null,
                'status_history' => $this->getStatusHistory($storeOrder),
                'buyer_info' => [
                    'id' => $storeOrder->order->user->id,
                    'name' => $storeOrder->order->user->full_name,
                    'email' => $storeOrder->order->user->email,
                    'phone' => $storeOrder->order->user->phone
                ],
                'store_info' => [
                    'id' => $storeOrder->store->id,
                    'name' => $storeOrder->store->store_name,
                    'email' => $storeOrder->store->store_email,
                    'phone' => $storeOrder->store->store_phone
                ]
            ];

            return ResponseHelper::success($trackingInfo, 'Store order tracking retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get order product information
     */
    private function getOrderProductInfo($order)
    {
        $storeOrder = $order->storeOrders->first();
        if (!$storeOrder || !$storeOrder->items->first()) {
            return 'No product information';
        }

        $firstItem = $storeOrder->items->first();
        return $firstItem->product->name ?? 'Unknown Product';
    }

    /**
     * Format order status for display
     */
    private function formatOrderStatus($status)
    {
        $statusMap = [
            'pending' => 'Order Placed',
            'order_placed' => 'Order Placed',
            'processing' => 'Processing',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'disputed' => 'Disputed',
            'uncompleted' => 'Uncompleted'
        ];

        return $statusMap[$status] ?? ucfirst($status);
    }

    /**
     * Get order status color
     */
    private function getOrderStatusColor($status)
    {
        $colors = [
            'pending' => 'red',
            'order_placed' => 'red',
            'processing' => 'yellow',
            'out_for_delivery' => 'blue',
            'delivered' => 'purple',
            'completed' => 'green',
            'disputed' => 'light-red',
            'uncompleted' => 'grey'
        ];

        return $colors[$status] ?? 'grey';
    }

    /**
     * Get status history for store order
     */
    private function getStatusHistory($storeOrder)
    {
        $statusHistory = [];
        
        // Add order placed status
        $statusHistory[] = [
            'status' => 'Order Placed',
            'date' => $storeOrder->created_at->format('d-m-Y h:iA'),
            'description' => 'Store order was placed successfully'
        ];
        
        // Add current status
        $statusHistory[] = [
            'status' => $this->formatOrderStatus($storeOrder->status),
            'date' => $storeOrder->updated_at->format('d-m-Y h:iA'),
            'description' => 'Current status'
        ];
        
        // Add tracking status if available
        if ($storeOrder->orderTracking->isNotEmpty()) {
            $tracking = $storeOrder->orderTracking->first();
            $statusHistory[] = [
                'status' => $this->formatOrderStatus($tracking->status),
                'date' => $tracking->updated_at->format('d-m-Y h:iA'),
                'description' => $tracking->notes ?? 'Tracking updated',
                'tracking_number' => $tracking->tracking_number,
                'carrier' => $tracking->carrier
            ];
        }
        
        return $statusHistory;
    }
}
