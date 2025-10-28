<?php

namespace App\Services\Buyer;

use App\Helpers\UserNotificationHelper;
use App\Models\{Order, StoreOrder, Wallet, Escrow, Store, OrderTracking};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PostAcceptancePaymentService
{
    /**
     * Process payment for an accepted order
     */
    public function processPayment(Order $order, array $paymentData): Order
    {
        return DB::transaction(function () use ($order, $paymentData) {
            // Validate order status
            if ($order->payment_status === 'paid') {
                throw ValidationException::withMessages(['order' => 'Order is already paid.']);
            }

            // Get the store order (each order has one store now)
            $storeOrder = $order->storeOrders()->first();

            if (!$storeOrder || $storeOrder->status !== 'accepted') {
                throw ValidationException::withMessages([
                    'order' => 'Order must be accepted by store before payment.'
                ]);
            }

            $acceptedStoreOrders = collect([$storeOrder]);

            // Recalculate amount based on accepted orders only
            $paymentAmount = $this->calculateAcceptedOrdersTotal($order);

            // Process payment based on method
            if ($order->payment_method === 'wallet') {
                $this->processWalletPayment($order, $paymentAmount);
            } else {
                $this->processCardPayment($order, $paymentAmount, $paymentData);
                
            }

            // Update order
            $order->update([
                'payment_status' => 'paid',
                'status' => 'accepted',
                'paid_at' => now(),
                'grand_total' => $paymentAmount, // Update with recalculated amount
            ]);

            // Update accepted store orders to 'paid'
            foreach ($acceptedStoreOrders as $storeOrder) {
                $storeOrder->update(['status' => 'paid']);

                // Update order tracking
                OrderTracking::where('store_order_id', $storeOrder->id)->update([
                    'status' => 'paid',
                    'notes' => 'Payment received. Order is being prepared.',
                ]);
            }

            // Create escrow records for accepted orders
            $this->createEscrowRecords($order, $acceptedStoreOrders);

            // Send notifications
            $this->sendPaymentNotifications($order, $acceptedStoreOrders);

            Log::info("Payment processed for order {$order->id}. Amount: {$paymentAmount}");

            return $order->fresh(['storeOrders.items', 'storeOrders.store']);
        });
    }

    /**
     * Calculate total for the order (each order has one store now)
     */
    private function calculateAcceptedOrdersTotal(Order $order): float
    {
        // Since each order has only one store order, use the order's grand_total
        return $order->grand_total;
    }

    /**
     * Process wallet payment
     */
    private function processWalletPayment(Order $order, float $amount): void
    {
        $user = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'shopping_balance' => 0]
        );

        if ($wallet->shopping_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet' => 'Insufficient wallet balance. Required: ₦' . number_format($amount, 2)
            ]);
        }

        // Deduct funds
        $wallet->shopping_balance -= $amount;
        $wallet->save();

        // Create transaction record
        $txId = 'COLTX-' . now()->format('YmdHis') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        \App\Models\Transaction::create([
            'tx_id'   => $txId,
            'amount'  => $amount,
            'status'  => 'success',
            'type'    => 'order_payment',
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Process card payment
     */
    private function processCardPayment(Order $order, float $amount, array $paymentData): void
    {
        // TODO: Integrate with payment gateway (Paystack, Flutterwave, etc.)
        // For now, we'll just create a transaction record
        
        $txId = $paymentData['tx_id'] ?? 'COLTX-' . now()->format('YmdHis') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        
        \App\Models\Transaction::create([
            'tx_id'   => $txId,
            'amount'  => $amount,
            'status'  => 'success',
            'type'    => 'order_payment',
            'order_id' => $order->id,
            'user_id' => $order->user_id,
        ]);
    }

    /**
     * Create escrow records for accepted orders
     */
    private function createEscrowRecords(Order $order, $acceptedStoreOrders): void
    {
        foreach ($acceptedStoreOrders as $storeOrder) {
            // Create single escrow record for the entire store order
            Escrow::create([
                'user_id'        => $order->user_id,
                'order_id'       => $order->id,
                'store_order_id' => $storeOrder->id,
                'order_item_id'  => null, // Store-order level escrow
                'amount'         => $storeOrder->subtotal_with_shipping, // Total amount including delivery
                'shipping_fee'   => $storeOrder->shipping_fee,
                'status'         => 'locked',
            ]);
        }
    }

    /**
     * Send payment notifications
     */
    private function sendPaymentNotifications(Order $order, $acceptedStoreOrders): void
    {
        // Notify buyer
        UserNotificationHelper::notify(
            $order->user_id,
            'Payment Successful',
            "Your payment for order #{$order->order_no} has been processed successfully. Amount: ₦" . number_format($order->grand_total, 2)
        );

        // Notify each store
        foreach ($acceptedStoreOrders as $storeOrder) {
            $store = Store::with('user')->find($storeOrder->store_id);
            if ($store && $store->user) {
                UserNotificationHelper::notify(
                    $store->user->id,
                    'Payment Received - Prepare Order',
                    "Payment received for order #{$order->order_no}. Please prepare the order for shipment. Amount: ₦" . number_format($storeOrder->subtotal_with_shipping, 2)
                );
            }
        }
    }

    /**
     * Get payment info for an order
     */
    public function getPaymentInfo(Order $order): array
    {
        $storeOrder = $order->storeOrders()->with('store')->first();

        if (!$storeOrder) {
            throw ValidationException::withMessages([
                'order' => 'Store order not found.'
            ]);
        }

        $paymentAmount = $order->grand_total;

        return [
            'order_no' => $order->order_no,
            'payment_method' => $order->payment_method,
            'amount_to_pay' => $paymentAmount,
            'store' => [
                'store_id' => $storeOrder->store_id,
                'store_name' => $storeOrder->store->store_name ?? 'Unknown',
                'amount' => $storeOrder->subtotal_with_shipping,
                'estimated_delivery' => $storeOrder->estimated_delivery_date,
                'delivery_method' => $storeOrder->delivery_method,
                'delivery_fee' => $storeOrder->shipping_fee,
                'items_subtotal' => $storeOrder->items_subtotal,
            ],
            'status' => $storeOrder->status,
            'can_pay' => $order->payment_status === 'pending' && $storeOrder->status === 'accepted',
        ];
    }

    /**
     * Check if order is ready for payment
     */
    public function isReadyForPayment(Order $order): bool
    {
        if ($order->payment_status === 'paid') {
            return false;
        }

        $storeOrder = $order->storeOrders()->first();
        return $storeOrder && $storeOrder->status === 'accepted';
    }
}

