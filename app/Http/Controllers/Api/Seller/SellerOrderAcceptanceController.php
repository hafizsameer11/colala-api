<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use App\Models\Store;
use App\Services\Seller\OrderAcceptanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SellerOrderAcceptanceController extends Controller
{
    protected $acceptanceService;

    public function __construct(OrderAcceptanceService $acceptanceService)
    {
        $this->acceptanceService = $acceptanceService;
    }

    /**
     * Get pending orders for seller's store
     */
    public function getPendingOrders(Request $request)
    {
        try {
            $userId = Auth::id();
            $store = Store::where('user_id', $userId)->first();

            if (!$store) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found for this user',
                ], 404);
            }

            $pendingOrders = $this->acceptanceService->getPendingOrders($store->id);

            $formattedOrders = $pendingOrders->map(function ($storeOrder) {
                return $this->formatStoreOrder($storeOrder);
            });

            return response()->json([
                'status' => 'success',
                'store'=>$store,
                'data' => [
                    'total' => $formattedOrders->count(),
                    'orders' => $formattedOrders,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get pending orders error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get pending orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept an order
     */
    public function acceptOrder(Request $request, $storeOrderId)
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
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store order not found',
                ], 404);
            }

            // Verify ownership
            $userId = Auth::id();
            if ($storeOrder->store->user_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This order does not belong to your store.',
                ], 403);
            }

            $storeOrder = $this->acceptanceService->acceptOrder($storeOrder, $request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Order accepted successfully',
                'data' => $this->formatStoreOrder($storeOrder),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Accept order error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to accept order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject an order
     */
    public function rejectOrder(Request $request, $storeOrderId)
    {
        try {
            $request->validate([
                'rejection_reason' => 'required|string|max:500',
            ]);

            $storeOrder = StoreOrder::with(['order', 'items', 'store'])->find($storeOrderId);

            if (!$storeOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store order not found',
                ], 404);
            }

            // Verify ownership
            $userId = Auth::id();
            if ($storeOrder->store->user_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This order does not belong to your store.',
                ], 403);
            }

            $storeOrder = $this->acceptanceService->rejectOrder(
                $storeOrder,
                $request->rejection_reason
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Order rejected successfully',
                'data' => $this->formatStoreOrder($storeOrder),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Reject order error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update delivery details
     */
    public function updateDeliveryDetails(Request $request, $storeOrderId)
    {
        try {
            $request->validate([
                'delivery_fee' => 'nullable|numeric|min:0',
                'estimated_delivery_date' => 'nullable|date',
                'delivery_method' => 'nullable|string|max:255',
                'delivery_notes' => 'nullable|string|max:500',
            ]);

            $storeOrder = StoreOrder::with(['order', 'items', 'store'])->find($storeOrderId);

            if (!$storeOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store order not found',
                ], 404);
            }

            // Verify ownership
            $userId = Auth::id();
            if ($storeOrder->store->user_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This order does not belong to your store.',
                ], 403);
            }

            $storeOrder = $this->acceptanceService->updateDeliveryDetails($storeOrder, $request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery details updated successfully',
                'data' => $this->formatStoreOrder($storeOrder),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Update delivery details error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update delivery details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get acceptance statistics
     */
    public function getAcceptanceStats(Request $request)
    {
        try {
            $userId = Auth::id();
            $store = Store::where('user_id', $userId)->first();

            if (!$store) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found for this user',
                ], 404);
            }

            $stats = $this->acceptanceService->getAcceptanceStats($store->id);

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Get acceptance stats error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get acceptance statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format store order for response
     */
    private function formatStoreOrder($storeOrder): array
    {
        return [
            'id' => $storeOrder->id,
            'order_no' => $storeOrder->order->order_no ?? null,
            'status' => $storeOrder->status,
            'items_subtotal' => $storeOrder->items_subtotal,
            'delivery_fee' => $storeOrder->shipping_fee,
            'shipping_fee' => $storeOrder->shipping_fee, // Keep for backward compatibility
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
}

