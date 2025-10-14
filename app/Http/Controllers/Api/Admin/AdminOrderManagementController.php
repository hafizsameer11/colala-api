<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreOrder;
use App\Models\OrderItem;
use App\Models\OrderTracking;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderManagementController extends Controller
{
    /**
     * Get all orders with filtering and pagination
     */
    public function getAllOrders(Request $request)
    {
        try {
            $query = StoreOrder::with([
                'order.user',
                'store.user',
                'items.product',
                'items.variant',
                'orderTracking',
                'deliveryPricing'
            ]);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('date_range')) {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                        break;
                }
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('order', function ($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%");
                })->orWhereHas('store', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $orders = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_orders' => StoreOrder::count(),
                'pending_orders' => StoreOrder::where('status', 'pending')->count(),
                'completed_orders' => StoreOrder::where('status', 'completed')->count(),
                'out_for_delivery' => StoreOrder::where('status', 'out_for_delivery')->count(),
                'delivered' => StoreOrder::where('status', 'delivered')->count(),
                'disputed' => StoreOrder::where('status', 'disputed')->count(),
            ];

            return ResponseHelper::success([
                'orders' => $this->formatOrdersData($orders),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed order information including products and tracking
     */
    public function getOrderDetails($storeOrderId)
    {
        try {
            $storeOrder = StoreOrder::with([
                'order.user',
                'order.deliveryAddress',
                'store',
                'store.addresses',
                'items.product.images',
                'items.product.variants',
                'items.product.reviews.user',
                'items.variant',
                'orderTracking',
                'deliveryPricing',
                'chat.messages',
                'chat.dispute'
            ])->findOrFail($storeOrderId);

            $orderData = [
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

            return ResponseHelper::success($orderData);
        } catch (Exception $e) {
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
                'status' => 'required|in:pending,processing,shipped,out_for_delivery,delivered,completed,disputed,cancelled',
                'notes' => 'nullable|string|max:500',
                'delivery_code' => 'nullable|string|max:50',
            ]);

            $storeOrder = StoreOrder::findOrFail($storeOrderId);

            // Create tracking entry
            OrderTracking::create([
                'store_order_id' => $storeOrder->id,
                'status' => $request->status,
                'notes' => $request->notes,
                'delivery_code' => $request->delivery_code,
            ]);

            // Update order status
            $storeOrder->update(['status' => $request->status]);

            return ResponseHelper::success([
                'order_id' => $storeOrder->id,
                'new_status' => $request->status,
                'tracking_entry' => [
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'delivery_code' => $request->delivery_code,
                    'created_at' => now(),
                ]
            ], 'Order status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get order tracking history
     */
    public function getOrderTracking($storeOrderId)
    {
        try {
            $storeOrder = StoreOrder::with(['orderTracking'])->findOrFail($storeOrderId);

            $tracking = $storeOrder->orderTracking()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($tracking) {
                    return [
                        'id' => $tracking->id,
                        'status' => $tracking->status,
                        'notes' => $tracking->notes,
                        'delivery_code' => $tracking->delivery_code,
                        'created_at' => $tracking->created_at,
                        'formatted_date' => $tracking->created_at->format('d-m-Y H:i A'),
                    ];
                });

            return ResponseHelper::success([
                'order_id' => $storeOrder->id,
                'current_status' => $storeOrder->status,
                'tracking_history' => $tracking,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on orders
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|in:update_status,mark_delivered,mark_completed',
                'order_ids' => 'required|array|min:1',
                'order_ids.*' => 'integer|exists:store_orders,id',
                'status' => 'required_if:action,update_status|in:pending,processing,shipped,out_for_delivery,delivered,completed,disputed,cancelled',
            ]);

            $orderIds = $request->order_ids;
            $action = $request->action;

            switch ($action) {
                case 'update_status':
                    StoreOrder::whereIn('id', $orderIds)->update(['status' => $request->status]);
                    return ResponseHelper::success(null, "Orders status updated to {$request->status}");
                
                case 'mark_delivered':
                    StoreOrder::whereIn('id', $orderIds)->update(['status' => 'delivered']);
                    return ResponseHelper::success(null, 'Orders marked as delivered');
                
                case 'mark_completed':
                    StoreOrder::whereIn('id', $orderIds)->update(['status' => 'completed']);
                    return ResponseHelper::success(null, 'Orders marked as completed');
            }
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics()
    {
        try {
            $stats = [
                'total_orders' => StoreOrder::count(),
                'pending_orders' => StoreOrder::where('status', 'pending')->count(),
                'processing_orders' => StoreOrder::where('status', 'processing')->count(),
                'shipped_orders' => StoreOrder::where('status', 'shipped')->count(),
                'out_for_delivery' => StoreOrder::where('status', 'out_for_delivery')->count(),
                'delivered_orders' => StoreOrder::where('status', 'delivered')->count(),
                'completed_orders' => StoreOrder::where('status', 'completed')->count(),
                'disputed_orders' => StoreOrder::where('status', 'disputed')->count(),
                'cancelled_orders' => StoreOrder::where('status', 'cancelled')->count(),
            ];

            // Monthly trends
            $monthlyStats = StoreOrder::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders
            ')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            return ResponseHelper::success([
                'current_stats' => $stats,
                'monthly_trends' => $monthlyStats,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
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
            'out_for_delivery' => 'orange',
            'delivered' => 'green',
            'completed' => 'green',
            'disputed' => 'red',
            'cancelled' => 'red'
        ];

        return $colors[$status] ?? 'gray';
    }

    /**
     * Format orders data for response
     */
    private function formatOrdersData($orders)
    {
        return $orders->map(function ($order) {
            return [
                'store_order_id' => $order->id,
                'order_number' => $order->order->order_no,
                'store_name' => $order->store->store_name,
                'seller_name' => $order->store->user->full_name,
                'customer_name' => $order->order->user->full_name,
                'status' => $order->status,
                'items_count' => $order->items->count(),
                'total_amount' => $order->subtotal_with_shipping,
                'created_at' => $order->created_at,
                'formatted_date' => $order->created_at->format('d-m-Y H:i A'),
            ];
        });
    }
}
