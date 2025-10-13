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
                'store.user',
                'items.product.images',
                'items.variant',
                'orderTracking',
                'deliveryPricing',
                'chat.messages'
            ])->findOrFail($storeOrderId);

            $orderData = [
                'order_info' => [
                    'store_order_id' => $storeOrder->id,
                    'order_number' => $storeOrder->order->order_no,
                    'status' => $storeOrder->status,
                    'created_at' => $storeOrder->created_at,
                    'updated_at' => $storeOrder->updated_at,
                ],
                'customer_info' => [
                    'user_id' => $storeOrder->order->user->id,
                    'name' => $storeOrder->order->user->full_name,
                    'email' => $storeOrder->order->user->email,
                    'phone' => $storeOrder->order->user->phone,
                ],
                'store_info' => [
                    'store_id' => $storeOrder->store->id,
                    'store_name' => $storeOrder->store->store_name,
                    'seller_name' => $storeOrder->store->user->full_name,
                    'seller_email' => $storeOrder->store->user->email,
                ],
                'order_items' => $storeOrder->items->map(function ($item) {
                    return [
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->name,
                        'sku' => $item->sku,
                        'variant' => $item->variant ? [
                            'id' => $item->variant->id,
                            'color' => $item->variant->color,
                            'size' => $item->variant->size,
                        ] : null,
                        'unit_price' => $item->unit_price,
                        'unit_discount_price' => $item->unit_discount_price,
                        'quantity' => $item->qty,
                        'line_total' => $item->line_total,
                        'product_images' => $item->product->images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'url' => asset('storage/' . $image->image_path),
                                'is_primary' => $image->is_primary,
                            ];
                        }),
                    ];
                }),
                'pricing' => [
                    'items_subtotal' => $storeOrder->items_subtotal,
                    'shipping_fee' => $storeOrder->shipping_fee,
                    'discount' => $storeOrder->discount,
                    'subtotal_with_shipping' => $storeOrder->subtotal_with_shipping,
                ],
                'delivery_info' => $storeOrder->deliveryPricing ? [
                    'delivery_pricing_id' => $storeOrder->deliveryPricing->id,
                    'name' => $storeOrder->deliveryPricing->name,
                    'price' => $storeOrder->deliveryPricing->price,
                    'estimated_days' => $storeOrder->deliveryPricing->estimated_days,
                ] : null,
                'order_tracking' => $storeOrder->orderTracking->map(function ($tracking) {
                    return [
                        'id' => $tracking->id,
                        'status' => $tracking->status,
                        'notes' => $tracking->notes,
                        'delivery_code' => $tracking->delivery_code,
                        'created_at' => $tracking->created_at,
                    ];
                }),
                'chat_info' => $storeOrder->chat ? [
                    'chat_id' => $storeOrder->chat->id,
                    'unread_messages' => $storeOrder->chat->messages()->where('is_read', false)->count(),
                    'last_message' => $storeOrder->chat->messages()->latest()->first()?->message,
                ] : null,
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
