<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreOrder;
use App\Models\OrderItem;
use App\Models\OrderTracking;
use App\Models\Store;
use App\Models\User;
use App\Services\Seller\OrderAcceptanceService;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminOrderManagementController extends Controller
{
    use PeriodFilterTrait;

    /**
     * @var OrderAcceptanceService
     */
    protected $acceptanceService;

    public function __construct(OrderAcceptanceService $acceptanceService)
    {
        $this->acceptanceService = $acceptanceService;
    }
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
                'deliveryPricing',
                'chat', // Needed for seller chat button on orders management
            ]);

            // Filter to only buyer orders (users with role='buyer' and no store)
            $query->whereHas('order', function ($orderQuery) {
                $orderQuery->whereHas('user', function ($userQuery) {
                    $userQuery->withoutGlobalScopes()
                        ->where(function ($q) {
                            $q->where('role', 'buyer')
                              ->orWhereNull('role')
                              ->orWhere('role', '');
                        })
                        ->whereDoesntHave('store'); // Exclude sellers (users with stores)
                });
            });

            // Account Officer sees only orders from assigned stores
            if (Auth::user()->role === 'account_officer') {
                $query->whereHas('store', function ($storeQuery) {
                    $storeQuery->where('account_officer_id', Auth::id());
                });
            }

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Validate period parameter
            $period = $request->get('period');
            if ($period && $period !== 'all_time' && $period !== 'null' && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            // Apply date filter (period > date_from/date_to > date_range)
            $this->applyDateFilter($query, $request);

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->whereHas('user', function ($userQuery) use ($search) {
                            $userQuery->withoutGlobalScopes()
                                     ->where('full_name', 'like', "%{$search}%")
                                     ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->orWhereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery->withoutGlobalScopes()
                                   ->where('store_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.product', function ($productQuery) use ($search) {
                        $productQuery->withoutGlobalScopes()
                                     ->where('name', 'like', "%{$search}%");
                    });
                });
            }

            // Check if export is requested
            if ($request->has('export') && $request->export == 'true') {
                $orders = $query->latest()->get();
                return ResponseHelper::success($orders, 'Orders exported successfully');
            }

            $orders = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics with period filtering
            // Filter to only buyer orders (same as main query)
            $buyerOrderFilter = function ($orderQuery) {
                $orderQuery->whereHas('user', function ($userQuery) {
                    $userQuery->withoutGlobalScopes()
                        ->where(function ($q) {
                            $q->where('role', 'buyer')
                              ->orWhereNull('role')
                              ->orWhere('role', '');
                        })
                        ->whereDoesntHave('store'); // Exclude sellers
                });
            };

            $totalOrdersQuery = StoreOrder::whereHas('order', $buyerOrderFilter);
            $pendingOrdersQuery = StoreOrder::whereHas('order', $buyerOrderFilter)
                ->where('status', 'pending');
            // Include both 'delivered' and 'completed' as completed orders
            $completedOrdersQuery = StoreOrder::whereHas('order', $buyerOrderFilter)
                ->whereIn('status', ['completed', 'delivered']);
            $outForDeliveryQuery = StoreOrder::whereHas('order', $buyerOrderFilter)
                ->where('status', 'out_for_delivery');
            $deliveredQuery = StoreOrder::whereHas('order', $buyerOrderFilter)
                ->where('status', 'delivered');
            $disputedQuery = StoreOrder::whereHas('order', $buyerOrderFilter)
                ->where('status', 'disputed');

            // Account Officer sees only stats from assigned stores
            if (Auth::user()->role === 'account_officer') {
                $accountOfficerId = Auth::id();
                $totalOrdersQuery->whereHas('store', function ($q) use ($accountOfficerId) {
                    $q->where('account_officer_id', $accountOfficerId);
                });
                $pendingOrdersQuery->whereHas('store', function ($q) use ($accountOfficerId) {
                    $q->where('account_officer_id', $accountOfficerId);
                });
                $completedOrdersQuery->whereHas('store', function ($q) use ($accountOfficerId) {
                    $q->where('account_officer_id', $accountOfficerId);
                });
                $outForDeliveryQuery->whereHas('store', function ($q) use ($accountOfficerId) {
                    $q->where('account_officer_id', $accountOfficerId);
                });
                $deliveredQuery->whereHas('store', function ($q) use ($accountOfficerId) {
                    $q->where('account_officer_id', $accountOfficerId);
                });
                $disputedQuery->whereHas('store', function ($q) use ($accountOfficerId) {
                    $q->where('account_officer_id', $accountOfficerId);
                });
            }

            if ($period) {
                $this->applyPeriodFilter($totalOrdersQuery, $period);
                $this->applyPeriodFilter($pendingOrdersQuery, $period);
                $this->applyPeriodFilter($completedOrdersQuery, $period);
                $this->applyPeriodFilter($outForDeliveryQuery, $period);
                $this->applyPeriodFilter($deliveredQuery, $period);
                $this->applyPeriodFilter($disputedQuery, $period);
            }

            $stats = [
                'total_orders' => $totalOrdersQuery->count(),
                'pending_orders' => $pendingOrdersQuery->count(),
                'completed_orders' => $completedOrdersQuery->count(),
                'out_for_delivery' => $outForDeliveryQuery->count(),
                'delivered' => $deliveredQuery->count(),
                'disputed' => $disputedQuery->count(),
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

            $order = $storeOrder->order;

            // Derive status for admin view: if parent order is soft-deleted, show as "deleted"
            $status = $storeOrder->status;
            $isDeletedOrder = false;
            if ($order && method_exists($order, 'trashed') && $order->trashed()) {
                $status = 'deleted';
                $isDeletedOrder = true;
            }

            $orderData = [
                'id' => $storeOrder->id,
                'order_no' => $order?->order_no ?? null,
                'status' => $status,
                'is_deleted' => $isDeletedOrder,
                'status_color' => $this->getOrderStatusColor($status),
                'store' => [
                    'id' => $storeOrder->store->id,
                    'name' => $storeOrder->store->store_name,
                    'email' => $storeOrder->store->store_email,
                    'phone' => $storeOrder->store->store_phone,
                    'location' => $storeOrder->store->addresses->first()?->full_address
                ],
                'customer' => $order && $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->full_name ?? 'Unknown Customer',
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                    'profile_picture' => $order->user->profile_picture ? asset('storage/' . $order->user->profile_picture) : null
                ] : null,
                'delivery_address' => $order && $order->deliveryAddress ? [
                    'id' => $order->deliveryAddress->id,
                    'full_address' => $order->deliveryAddress->full_address,
                    'state' => $order->deliveryAddress->state,
                    'local_government' => $order->deliveryAddress->local_government,
                    'contact_name' => $order->deliveryAddress->contact_name,
                    'contact_phone' => $order->deliveryAddress->contact_phone
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
                                    'user' => $review->user ? [
                                        'id' => $review->user->id,
                                        'name' => $review->user ? $review->user->full_name : 'Unknown User',
                                        'profile_picture' => $review->user->profile_picture ? asset('storage/' . $review->user->profile_picture) : null
                                    ] : null,
                                    'rating' => $review->rating,
                                    'comment' => $review->comment,
                                    'created_at' => $review->created_at ? $review->created_at->format('d-m-Y H:i:s') : null
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

            // Send notifications for order status update
            $this->sendOrderStatusNotification($storeOrder, $request->status, $request->notes);

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
     * Admin: Accept a store order on behalf of the seller (including delivery fee & details)
     *
     * This mirrors the seller flow:
     * - status must be `pending_acceptance`
     * - sets delivery_fee (shipping_fee), estimated_delivery_date, delivery_method, delivery_notes
     * - recalculates subtotal_with_shipping and parent Order totals
     * - updates tracking and sends notification to buyer
     */
    public function acceptOrderOnBehalf(Request $request, $storeOrderId)
    {
        try {
            $request->validate([
                'delivery_fee' => 'required|numeric|min:0',
                'estimated_delivery_date' => 'nullable|date|after:today',
                'delivery_method' => 'nullable|string|max:255',
                'delivery_notes' => 'nullable|string|max:500',
            ]);

            $storeOrder = StoreOrder::with(['order', 'items', 'store'])->find($storeOrderId);

            if (!$storeOrder) {
                return ResponseHelper::error('Store order not found', 404);
            }

            // Reuse core seller acceptance logic (no ownership check for admin)
            $storeOrder = $this->acceptanceService->acceptOrder($storeOrder, $request->all());

            return ResponseHelper::success(
                $this->formatSingleStoreOrder($storeOrder),
                'Order accepted successfully by admin on behalf of seller'
            );
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Admin acceptOrderOnBehalf error: ' . $e->getMessage(), [
                'store_order_id' => $storeOrderId ?? null,
            ]);
            return ResponseHelper::error('Failed to accept order: ' . $e->getMessage(), 500);
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
    public function getOrderStatistics(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            $totalOrdersQuery = StoreOrder::query();
            $pendingOrdersQuery = StoreOrder::where('status', 'pending');
            $processingOrdersQuery = StoreOrder::where('status', 'processing');
            $shippedOrdersQuery = StoreOrder::where('status', 'shipped');
            $outForDeliveryQuery = StoreOrder::where('status', 'out_for_delivery');
            $deliveredOrdersQuery = StoreOrder::where('status', 'delivered');
            // Include both 'delivered' and 'completed' as completed orders
            $completedOrdersQuery = StoreOrder::whereIn('status', ['completed', 'delivered']);
            $disputedOrdersQuery = StoreOrder::where('status', 'disputed');
            $cancelledOrdersQuery = StoreOrder::where('status', 'cancelled');

            if ($period) {
                $this->applyPeriodFilter($totalOrdersQuery, $period);
                $this->applyPeriodFilter($pendingOrdersQuery, $period);
                $this->applyPeriodFilter($processingOrdersQuery, $period);
                $this->applyPeriodFilter($shippedOrdersQuery, $period);
                $this->applyPeriodFilter($outForDeliveryQuery, $period);
                $this->applyPeriodFilter($deliveredOrdersQuery, $period);
                $this->applyPeriodFilter($completedOrdersQuery, $period);
                $this->applyPeriodFilter($disputedOrdersQuery, $period);
                $this->applyPeriodFilter($cancelledOrdersQuery, $period);
            }

            $stats = [
                'total_orders' => $totalOrdersQuery->count(),
                'pending_orders' => $pendingOrdersQuery->count(),
                'processing_orders' => $processingOrdersQuery->count(),
                'shipped_orders' => $shippedOrdersQuery->count(),
                'out_for_delivery' => $outForDeliveryQuery->count(),
                'delivered_orders' => $deliveredOrdersQuery->count(),
                'completed_orders' => $completedOrdersQuery->count(),
                'disputed_orders' => $disputedOrdersQuery->count(),
                'cancelled_orders' => $cancelledOrdersQuery->count(),
            ];

            // Monthly trends - include both 'delivered' and 'completed' as completed orders
            $monthlyStats = StoreOrder::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status IN ("completed", "delivered") THEN 1 ELSE 0 END) as completed_orders
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
     * Format a single store order for detailed response (mirrors seller acceptance response)
     */
    private function formatSingleStoreOrder(StoreOrder $storeOrder): array
    {
        return [
            'id' => $storeOrder->id,
            'order_no' => $storeOrder->order->order_no ?? null,
            'status' => $storeOrder->status,
            'items_subtotal' => $storeOrder->items_subtotal,
            'delivery_fee' => $storeOrder->shipping_fee,
            'shipping_fee' => $storeOrder->shipping_fee, // Backward compatibility
            'subtotal_with_shipping' => $storeOrder->subtotal_with_shipping,
            'estimated_delivery_date' => $storeOrder->estimated_delivery_date,
            'delivery_method' => $storeOrder->delivery_method,
            'delivery_notes' => $storeOrder->delivery_notes,
            'rejection_reason' => $storeOrder->rejection_reason,
            'accepted_at' => $storeOrder->accepted_at ? $storeOrder->accepted_at->format('Y-m-d H:i:s') : null,
            'rejected_at' => $storeOrder->rejected_at ? $storeOrder->rejected_at->format('Y-m-d H:i:s') : null,
            'created_at' => $storeOrder->created_at ? $storeOrder->created_at->format('Y-m-d H:i:s') : null,
            'customer' => [
                'id' => $storeOrder->order->user->id ?? null,
                'name' => $storeOrder->order->user->full_name ?? null,
                'email' => $storeOrder->order->user->email ?? null,
                'phone' => $storeOrder->order->user->phone ?? null,
            ],
            'items' => $storeOrder->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'qty' => $item->qty,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ];
            }),
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
            $parentOrder = $order->order;

            // Override status for soft-deleted parent orders
            $status = $order->status;
            if ($parentOrder && method_exists($parentOrder, 'trashed') && $parentOrder->trashed()) {
                $status = 'deleted';
            }

            return [
                'store_order_id' => $order->id,
                'order_number' => $parentOrder ? $parentOrder->order_no : null,
                'store_name' => $order->store ? $order->store->store_name : null,
                'seller_name' => $order->store ? $order->store->store_name : null,
                'customer_name' => $parentOrder && $parentOrder->user ? $parentOrder->user->full_name : 'Unknown Customer',
                // For frontend chat integration on seller orders page
                'chat_id' => $order->chat ? $order->chat->id : null,
                // Buyer/user id aliases so frontend normalizer can find a user id
                'buyer_id' => $parentOrder ? $parentOrder->user_id : null,
                'status' => $status,
                'items_count' => $order->items ? $order->items->count() : 0,
                'total_amount' => $order->subtotal_with_shipping,
                'created_at' => $order->created_at,
                'formatted_date' => $order->created_at ? $order->created_at->format('d-m-Y H:i A') : null,
            ];
        });
    }

    /**
     * Send order status update notifications
     */
    private function sendOrderStatusNotification(StoreOrder $storeOrder, string $status, ?string $notes = null)
    {
        $order = $storeOrder->order;
        $store = $storeOrder->store;

        $statusMessages = [
            'pending' => 'Your order is pending',
            'processing' => 'Your order is being processed',
            'shipped' => 'Your order has been shipped',
            'out_for_delivery' => 'Your order is out for delivery',
            'delivered' => 'Your order has been delivered',
            'completed' => 'Your order has been completed',
            'disputed' => 'Your order is under dispute',
            'cancelled' => 'Your order has been cancelled'
        ];

        $statusMessage = $statusMessages[$status] ?? 'Your order status has been updated';
        $notesText = $notes ? " Note: {$notes}" : '';

        // Notify the buyer
        UserNotificationHelper::notify(
            $order->user_id,
            'Order Status Update',
            "Order #{$order->order_no} - {$statusMessage}.{$notesText}",
            [
                'type' => 'order_status_update',
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'store_order_id' => $storeOrder->id,
                'status' => $status,
                'notes' => $notes
            ]
        );

        // Notify the store owner
        if ($store && $store->user) {
            UserNotificationHelper::notify(
                $store->user->id,
                'Order Status Updated',
                "Order #{$order->order_no} status updated to: " . ucfirst(str_replace('_', ' ', $status)) . ".{$notesText}",
                [
                    'type' => 'order_status_update',
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'store_order_id' => $storeOrder->id,
                    'status' => $status,
                    'notes' => $notes
                ]
            );
        }
    }
}
