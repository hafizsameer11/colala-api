<?php

namespace App\Services\Seller;

use App\Helpers\UserNotificationHelper;
use App\Models\{StoreOrder, Order, Store, OrderTracking};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderAcceptanceService
{
    /**
     * Accept a store order and set delivery details
     */
    public function acceptOrder(StoreOrder $storeOrder, array $data): StoreOrder
    {
        return DB::transaction(function () use ($storeOrder, $data) {
            // Validate current status
            if ($storeOrder->status !== 'pending_acceptance') {
                throw ValidationException::withMessages([
                    'status' => 'This order cannot be accepted. Current status: ' . $storeOrder->status
                ]);
            }

            // Get delivery fee (seller sets this)
            $deliveryFee = isset($data['delivery_fee']) ? (float) $data['delivery_fee'] : 0;

            // Recalculate subtotal with new shipping fee
            $subtotalWithShipping = $storeOrder->items_subtotal + $deliveryFee;

            // Update store order
            $storeOrder->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'shipping_fee' => $deliveryFee,
                'subtotal_with_shipping' => $subtotalWithShipping,
                'estimated_delivery_date' => $data['estimated_delivery_date'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? null,
                'delivery_notes' => $data['delivery_notes'] ?? null,
            ]);

            // Update order tracking
            OrderTracking::where('store_order_id', $storeOrder->id)->update([
                'status' => 'accepted',
                'notes' => 'Order accepted by store. Awaiting payment from buyer.',
            ]);

            // Check if all store orders are accepted
            $this->updateOverallOrderStatus($storeOrder->order_id);

            // Notify buyer
            $store = Store::find($storeOrder->store_id);
            $storeName = $store->store_name ?? 'Store';
            
            $deliveryInfo = '';
            if ($storeOrder->estimated_delivery_date) {
                $deliveryInfo = " Estimated delivery: " . date('M d, Y', strtotime($storeOrder->estimated_delivery_date));
            }
            if ($deliveryFee > 0) {
                $deliveryInfo .= " Delivery fee: ₦" . number_format($deliveryFee, 2);
            }

            UserNotificationHelper::notify(
                $storeOrder->order->user_id,
                'Order Accepted - Payment Required',
                "{$storeName} has accepted your order #{$storeOrder->order->order_no}.{$deliveryInfo} Please proceed to payment."
            );

            Log::info("Store order {$storeOrder->id} accepted by store {$storeOrder->store_id} with delivery fee: {$deliveryFee}");

            return $storeOrder->fresh(['order', 'items', 'store']);
        });
    }

    /**
     * Reject a store order
     */
    public function rejectOrder(StoreOrder $storeOrder, string $reason): StoreOrder
    {
        return DB::transaction(function () use ($storeOrder, $reason) {
            // Validate current status
            if ($storeOrder->status !== 'pending_acceptance') {
                throw ValidationException::withMessages([
                    'status' => 'This order cannot be rejected. Current status: ' . $storeOrder->status
                ]);
            }

            // Update store order
            $storeOrder->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Update order tracking
            OrderTracking::where('store_order_id', $storeOrder->id)->update([
                'status' => 'rejected',
                'notes' => 'Order rejected by store. Reason: ' . $reason,
            ]);

            // Check if all store orders are rejected
            $this->updateOverallOrderStatus($storeOrder->order_id);

            // Notify buyer
            $store = Store::find($storeOrder->store_id);
            $storeName = $store->store_name ?? 'Store';

            UserNotificationHelper::notify(
                $storeOrder->order->user_id,
                'Order Rejected',
                "{$storeName} has rejected your order #{$storeOrder->order->order_no}. Reason: {$reason}"
            );

            Log::info("Store order {$storeOrder->id} rejected by store {$storeOrder->store_id}");

            return $storeOrder->fresh(['order', 'items', 'store']);
        });
    }

    /**
     * Update delivery details for an accepted order
     */
    public function updateDeliveryDetails(StoreOrder $storeOrder, array $data): StoreOrder
    {
        // Validate current status
        if (!in_array($storeOrder->status, ['accepted', 'paid'])) {
            throw ValidationException::withMessages([
                'status' => 'Delivery details can only be updated for accepted or paid orders.'
            ]);
        }

        // Can't update delivery fee if order is already paid
        if ($storeOrder->status === 'paid' && isset($data['delivery_fee'])) {
            throw ValidationException::withMessages([
                'delivery_fee' => 'Cannot update delivery fee for paid orders.'
            ]);
        }

        $updateData = [
            'estimated_delivery_date' => $data['estimated_delivery_date'] ?? $storeOrder->estimated_delivery_date,
            'delivery_method' => $data['delivery_method'] ?? $storeOrder->delivery_method,
            'delivery_notes' => $data['delivery_notes'] ?? $storeOrder->delivery_notes,
        ];

        // Update delivery fee if provided and order not paid yet
        if (isset($data['delivery_fee']) && $storeOrder->status === 'accepted') {
            $deliveryFee = (float) $data['delivery_fee'];
            $updateData['shipping_fee'] = $deliveryFee;
            $updateData['subtotal_with_shipping'] = $storeOrder->items_subtotal + $deliveryFee;
        }

        $storeOrder->update($updateData);

        // Notify buyer if order is paid or delivery fee changed
        if ($storeOrder->status === 'paid' || isset($data['delivery_fee'])) {
            $store = Store::find($storeOrder->store_id);
            $storeName = $store->store_name ?? 'Store';

            $message = "{$storeName} has updated delivery details for order #{$storeOrder->order->order_no}.";
            if (isset($data['delivery_fee'])) {
                $message .= " New delivery fee: ₦" . number_format($data['delivery_fee'], 2);
            }

            UserNotificationHelper::notify(
                $storeOrder->order->user_id,
                'Delivery Details Updated',
                $message
            );
        }

        return $storeOrder->fresh(['order', 'items', 'store']);
    }

    /**
     * Update overall order status based on store order
     * Since each order now has only one store, this is simpler
     */
    private function updateOverallOrderStatus(int $orderId): void
    {
        $order = Order::with('storeOrders')->find($orderId);
        if (!$order) {
            return;
        }

        // Each order has only one store order now
        $storeOrder = $order->storeOrders->first();
        if (!$storeOrder) {
            return;
        }

        // Sync order status with store order status
        if ($storeOrder->status === 'rejected') {
            $order->update(['status' => 'cancelled']);
        } elseif ($storeOrder->status === 'accepted') {
            $order->update(['status' => 'accepted']);
        } elseif ($storeOrder->status === 'paid') {
            $order->update(['status' => 'accepted', 'payment_status' => 'paid']);
        }
    }

    /**
     * Get pending orders for a store
     */
    public function getPendingOrders(int $storeId)
    {
        return StoreOrder::with(['order.user', 'items.product', 'store'])
            ->where('store_id', $storeId)
            ->where('status', 'pending_acceptance')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get acceptance statistics for a store
     */
    public function getAcceptanceStats(int $storeId): array
    {
        $pending = StoreOrder::where('store_id', $storeId)
            ->where('status', 'pending_acceptance')
            ->count();

        $accepted = StoreOrder::where('store_id', $storeId)
            ->where('status', 'accepted')
            ->count();

        $rejected = StoreOrder::where('store_id', $storeId)
            ->where('status', 'rejected')
            ->count();

        $totalProcessed = $accepted + $rejected;
        $acceptanceRate = $totalProcessed > 0 ? round(($accepted / $totalProcessed) * 100, 2) : 0;

        return [
            'pending' => $pending,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'acceptance_rate' => $acceptanceRate,
        ];
    }
}

