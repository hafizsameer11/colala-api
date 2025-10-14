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
     * Get all buyer orders with summary stats
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with(['user', 'storeOrders.store', 'storeOrders.orderTracking'])
                ->whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                });

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->whereHas('storeOrders', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
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

            $orders = $query->latest()->paginate(15);

            // Get summary stats (only for buyers)
            $totalOrders = Order::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->count();
            $pendingOrders = Order::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->whereHas('storeOrders', function ($q) {
                $q->whereIn('status', ['pending', 'order_placed', 'processing']);
            })->count();
            $completedOrders = Order::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->whereHas('storeOrders', function ($q) {
                $q->where('status', 'completed');
            })->count();

            $orders->getCollection()->transform(function ($order) {
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

            $summaryStats = [
                'total_orders' => [
                    'count' => $totalOrders,
                    'increase' => 5, // Mock data
                    'color' => 'red'
                ],
                'pending_orders' => [
                    'count' => $pendingOrders,
                    'increase' => 5, // Mock data
                    'color' => 'red'
                ],
                'completed_orders' => [
                    'count' => $completedOrders,
                    'increase' => 5, // Mock data
                    'color' => 'red'
                ]
            ];

            return ResponseHelper::success([
                'orders' => $orders,
                'summary_stats' => $summaryStats
            ], 'Buyer orders retrieved successfully');
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
     * Get detailed order information
     */
    public function orderDetails($orderId)
    {
        try {
            $order = Order::with([
                'user',
                'storeOrders.store.user',
                'storeOrders.items.product.images',
                'storeOrders.items.product.variants',
                'storeOrders.orderTracking',
                'deliveryAddress'
            ])->whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->findOrFail($orderId);

            $storeOrder = $order->storeOrders->first();
            
            $orderDetails = [
                'order_info' => [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'status' => $storeOrder ? $this->formatOrderStatus($storeOrder->status) : 'Unknown',
                    'status_color' => $this->getOrderStatusColor($storeOrder ? $storeOrder->status : 'unknown'),
                    'total_amount' => 'N' . number_format($order->grand_total, 2),
                    'order_date' => $order->created_at->format('d-m-Y h:iA'),
                    'updated_at' => $order->updated_at->format('d-m-Y h:iA')
                ],
                'buyer_info' => [
                    'id' => $order->user->id,
                    'name' => $order->user->full_name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                    'profile_picture' => $order->user->profile_picture ? asset('storage/' . $order->user->profile_picture) : null
                ],
                'store_info' => $storeOrder ? [
                    'id' => $storeOrder->store->id,
                    'name' => $storeOrder->store->store_name,
                    'seller' => [
                        'id' => $storeOrder->store->user->id,
                        'name' => $storeOrder->store->user->full_name,
                        'email' => $storeOrder->store->user->email,
                        'phone' => $storeOrder->store->user->phone
                    ],
                    'contact' => [
                        'email' => $storeOrder->store->store_email,
                        'phone' => $storeOrder->store->store_phone
                    ]
                ] : null,
                'delivery_address' => $order->deliveryAddress ? [
                    'full_address' => $order->deliveryAddress->address,
                    'city' => $order->deliveryAddress->city,
                    'state' => $order->deliveryAddress->state,
                    'country' => $order->deliveryAddress->country,
                    'postal_code' => $order->deliveryAddress->postal_code
                ] : null,
                'order_items' => $storeOrder ? $storeOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'description' => $item->product->description,
                            'images' => $item->product->images->map(function ($image) {
                                return [
                                    'id' => $image->id,
                                    'path' => asset('storage/' . $image->path),
                                    'is_main' => $image->is_main
                                ];
                            })
                        ],
                        'variants' => $item->product->variants->map(function ($variant) {
                            return [
                                'id' => $variant->id,
                                'name' => $variant->name,
                                'value' => $variant->value,
                                'price' => $variant->price
                            ];
                        }),
                        'quantity' => $item->qty,
                        'unit_price' => 'N' . number_format($item->price, 2),
                        'total_price' => 'N' . number_format($item->price * $item->qty, 2)
                    ];
                }) : [],
                'tracking_info' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? [
                    'current_status' => $storeOrder->orderTracking->first()->status,
                    'tracking_number' => $storeOrder->orderTracking->first()->tracking_number,
                    'carrier' => $storeOrder->orderTracking->first()->carrier,
                    'estimated_delivery' => $storeOrder->orderTracking->first()->estimated_delivery,
                    'last_updated' => $storeOrder->orderTracking->first()->updated_at->format('d-m-Y h:iA'),
                    'notes' => $storeOrder->orderTracking->first()->notes
                ] : null,
                'payment_info' => [
                    'payment_method' => $order->payment_method ?? 'Unknown',
                    'payment_status' => $order->payment_status ?? 'Unknown',
                    'transaction_id' => $order->transaction_id ?? null
                ]
            ];

            return ResponseHelper::success($orderDetails, 'Order details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $orderId)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending,order_placed,processing,out_for_delivery,delivered,completed,disputed,uncompleted',
                'tracking_number' => 'nullable|string',
                'carrier' => 'nullable|string',
                'estimated_delivery' => 'nullable|date',
                'notes' => 'nullable|string'
            ]);

            $order = Order::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->findOrFail($orderId);
            $storeOrder = $order->storeOrders->first();

            if ($storeOrder) {
                $storeOrder->update(['status' => $request->status]);
            }

            // Update or create tracking info
            if ($storeOrder) {
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
            }

            return ResponseHelper::success(null, 'Order status updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get order tracking history
     */
    public function orderTracking($orderId)
    {
        try {
            $order = Order::with(['storeOrders.orderTracking', 'storeOrders'])
                ->whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->findOrFail($orderId);
            
            $storeOrder = $order->storeOrders->first();
            
            $trackingInfo = [
                'order_no' => $order->order_no,
                'current_status' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->status : 'Unknown',
                'tracking_number' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->tracking_number : null,
                'carrier' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->carrier : null,
                'estimated_delivery' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->estimated_delivery : null,
                'last_updated' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->updated_at->format('d-m-Y h:iA') : null,
                'notes' => $storeOrder && $storeOrder->orderTracking->isNotEmpty() ? $storeOrder->orderTracking->first()->notes : null,
                'status_history' => $this->getStatusHistory($order)
            ];

            return ResponseHelper::success($trackingInfo, 'Order tracking retrieved successfully');
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
     * Get status history for order
     */
    private function getStatusHistory($order)
    {
        // This would typically come from a status_history table
        // For now, return basic history
        return [
            [
                'status' => 'Order Placed',
                'date' => $order->created_at->format('d-m-Y h:iA'),
                'description' => 'Order was placed successfully'
            ],
            [
                'status' => $this->formatOrderStatus($order->storeOrders->first()->status ?? 'pending'),
                'date' => $order->updated_at->format('d-m-Y h:iA'),
                'description' => 'Current status'
            ]
        ];
    }
}
