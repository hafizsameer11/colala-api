<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CheckoutPreviewRequest;
use App\Http\Requests\PaymentConfirmationReques;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Store;
use App\Services\Buyer\CartService;
use App\Services\Buyer\CheckoutService;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cartSvc, private CheckoutService $chk) {}

    public function preview(CheckoutPreviewRequest $req)
    {
        try {
            $cart = $this->cartSvc->getOrCreateCart($req->user()->id);
            $data = $this->chk->preview(
                $cart,
                (int) $req->delivery_address_id,
                $req->payment_method
            );
            return ResponseHelper::success($data);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function place(CheckoutPreviewRequest $req)
    {
        try {
            $cart = $this->cartSvc->getOrCreateCart($req->user()->id);
            $preview = $this->chk->preview(
                $cart,
                (int) $req->delivery_address_id,
                $req->payment_method
            );
            $orders = $this->chk->place($cart, $preview); // Returns array of orders now
            
            return ResponseHelper::success([
                'message' => count($orders) . ' order(s) created successfully',
                'orders' => $orders,
                'total_orders' => count($orders),
            ]);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function paymentConfirmation(PaymentConfirmationReques $req)
    {
        try {
            $data   = $req->validated();
            $amount = $data['amount'];
            $orderId = $data['order_id'];
            $txId    = $data['tx_id'];

            $order = Order::with('storeOrders.items.product')->find($orderId);
            if (!$order) {
                return ResponseHelper::error('Order not found', 404);
            }

            // Check if store has accepted the order
            $storeOrder = $order->storeOrders()->first();
            if (!$storeOrder || $storeOrder->status !== 'accepted') {
                return ResponseHelper::error('Order must be accepted by store before payment', 400);
            }

            if ($order->payment_status === 'paid') {
                return ResponseHelper::error('Order is already paid', 400);
            }

            // Update order status
            $order->payment_status = 'paid';
            $order->status = 'accepted';
            $order->paid_at = now();
            $order->save();

            // Update store order status
            $storeOrder->update(['status' => 'paid']);

            // Update order tracking
            \App\Models\OrderTracking::where('store_order_id', $storeOrder->id)->update([
                'status' => 'paid',
                'notes' => 'Payment received. Order is being prepared.',
            ]);

            // Create transaction
            Transaction::create([
                'tx_id' => $txId,
                'amount' => $amount,
                'status' => 'success',
                'type' => 'order_payment',
                'order_id' => $orderId,
                'user_id' => $req->user()->id,
            ]);

            // ✅ Create escrow for the entire store order
            \App\Models\Escrow::create([
                'user_id'        => $req->user()->id,
                'order_id'       => $order->id,
                'store_order_id' => $storeOrder->id,
                'order_item_id'  => null, // Store-order level escrow
                'amount'         => $storeOrder->subtotal_with_shipping, // Total amount including delivery
                'shipping_fee'   => $storeOrder->shipping_fee,
                'status'         => 'locked',
            ]);

            // Send payment confirmation notifications
            $this->sendPaymentConfirmationNotifications($order);

            return ResponseHelper::success(['message' => 'Payment confirmed successfully.']);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send payment confirmation notifications
     */
    private function sendPaymentConfirmationNotifications(Order $order)
    {
        // Notify the buyer
        UserNotificationHelper::notify(
            $order->user_id,
            'Payment Confirmed',
            "Payment for order #{$order->order_no} has been confirmed. Amount: ₦" . number_format($order->grand_total, 2)
        );

        // Notify each store owner
        foreach ($order->storeOrders as $storeOrder) {
            $store = Store::with('user')->find($storeOrder->store_id);
            if ($store && $store->user) {
                UserNotificationHelper::notify(
                    $store->user->id,
                    'Payment Received',
                    "Payment confirmed for order #{$order->order_no}. Amount: ₦" . number_format($storeOrder->subtotal_with_shipping, 2)
                );
            }
        }
    }
}
