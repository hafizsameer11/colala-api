<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Buyer\PostAcceptancePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BuyerOrderPaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PostAcceptancePaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get payment information for an order
     */
    public function getPaymentInfo($orderId)
    {
        try {
            $order = Order::with(['storeOrders.store'])->find($orderId);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            // Verify ownership
            if ($order->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This order does not belong to you.',
                ], 403);
            }

            $paymentInfo = $this->paymentService->getPaymentInfo($order);

            return response()->json([
                'status' => 'success',
                'data' => $paymentInfo,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Get payment info error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get payment information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process payment for an accepted order
     */
    public function processPayment(Request $request, $orderId)
    {
        try {
            $request->validate([
                'tx_id' => 'nullable|string', // For card payments
            ]);

            $order = Order::with(['storeOrders.items.product'])->find($orderId);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            // Verify ownership
            if ($order->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This order does not belong to you.',
                ], 403);
            }

            // Check if ready for payment
            if (!$this->paymentService->isReadyForPayment($order)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order is not ready for payment. Please wait for store acceptance.',
                ], 422);
            }

            $order = $this->paymentService->processPayment($order, $request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'data' => $this->formatOrder($order),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Process payment error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel an unpaid order
     */
    public function cancelOrder($orderId)
    {
        try {
            $order = Order::with('storeOrders')->find($orderId);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            // Verify ownership
            if ($order->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This order does not belong to you.',
                ], 403);
            }

            // Can only cancel unpaid orders
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot cancel a paid order.',
                ], 422);
            }

            // Update order and store orders
            $order->update(['status' => 'cancelled']);
            $order->storeOrders()->update(['status' => 'cancelled']);

            return response()->json([
                'status' => 'success',
                'message' => 'Order cancelled successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Cancel order error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format order for response
     */
    private function formatOrder($order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'items_total' => $order->items_total,
            'shipping_total' => $order->shipping_total,
            'platform_fee' => $order->platform_fee,
            'grand_total' => $order->grand_total,
            'paid_at' => $order->paid_at ? $order->paid_at->format('Y-m-d H:i:s') : null,
            'created_at' => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
            'store_orders' => $order->storeOrders->map(function ($storeOrder) {
                return [
                    'id' => $storeOrder->id,
                    'store_id' => $storeOrder->store_id,
                    'store_name' => $storeOrder->store->store_name ?? null,
                    'status' => $storeOrder->status,
                    'subtotal_with_shipping' => $storeOrder->subtotal_with_shipping,
                    'estimated_delivery_date' => $storeOrder->estimated_delivery_date,
                    'delivery_method' => $storeOrder->delivery_method,
                ];
            }),
        ];
    }
}

