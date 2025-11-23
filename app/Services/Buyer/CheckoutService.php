<?php

namespace App\Services\Buyer;

use App\Helpers\ProductStatHelper;
use App\Helpers\UserNotificationHelper;
use App\Models\{Cart, Chat, Escrow, Order, StoreOrder, OrderItem, Wallet, User, Store};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function preview(Cart $cart, int $addressId, string $paymentMethod): array
    {
        if ($cart->items()->count() === 0) {
            throw ValidationException::withMessages(['cart' => 'Cart is empty.']);
        }

        $cart->load(['items.product', 'items.variant']);
        $grouped = $cart->items->groupBy('store_id');

        $summaryStores = [];
        $itemsTotal = 0;
        $shippingTotal = 0;     
        $platformFee = 0;
        $discountTotal = 0;

        foreach ($grouped as $storeId => $items) {
            $itemsSubtotal = 0;
            $lines = [];

            foreach ($items as $i) {
                $unit = $i->unit_discount_price ?? $i->unit_price ?? $i->product->discount_price ?? $i->product->price;
                $lineTotal = $unit * $i->qty;
                $itemsSubtotal += $lineTotal;
                // ✅ Shipping fee will be set by seller when accepting order
                // No need to calculate delivery fee here

                $lines[] = [
                    'product_id' => $i->product_id,
                    'variant_id' => $i->variant_id,
                    'name' => $i->product->name,
                    'qty' => $i->qty,
                    'unit' => $unit,
                    'line_total' => $lineTotal,
                ];
            }

            $itemsTotal += $itemsSubtotal;

            $summaryStores[] = [
                'store_id' => $storeId,
                'items_subtotal' => $itemsSubtotal,
                'subtotal_with_shipping' => $itemsSubtotal, // Will be updated when seller sets shipping
                'shipping_fee' => 0, // ✅ Shipping will be set by seller during order acceptance
                'lines' => $lines,
            ];
        }

        // ✅ Shipping total is 0 - will be added when seller accepts order
        $shippingTotal = 0;
        $platformFee = round($itemsTotal * 0.015, 2); // 1.5%
        $grand = $itemsTotal + $shippingTotal + $platformFee - $discountTotal;

        return [
            'address_id'   => $addressId,
            'payment_method' => $paymentMethod,
            'items_total'  => $itemsTotal,
            'shipping_total' => $shippingTotal,
            'platform_fee' => $platformFee,
            'discount_total' => $discountTotal,
            'grand_total'  => $grand,
            'stores'       => $summaryStores,
        ];
    }

    public function place(Cart $cart, array $preview): array
    {
        return DB::transaction(function () use ($cart, $preview) {
            $orders = [];

            // ✅ Create separate order for each store
            foreach ($preview['stores'] as $S) {
                // Calculate per-store totals
                $storeItemsTotal = $S['items_subtotal'];
                $storeShippingTotal = 0; // ✅ Shipping will be set by seller when accepting order
                $storePlatformFee = 0; // 1.5%
                $storeGrandTotal = $storeItemsTotal + $storeShippingTotal + $storePlatformFee;

                // Create individual order per store
                $order = Order::create([
                    'order_no' => 'COL-' . now()->format('Ymd') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                    'user_id' => $cart->user_id,
                    'delivery_address_id' => $preview['address_id'],
                    'payment_method' => $preview['payment_method'],
                    'payment_status' => 'pending',
                    'status' => 'pending',
                    'items_total' => $storeItemsTotal,
                    'shipping_total' => $storeShippingTotal,
                    'platform_fee' => $storePlatformFee,
                    'discount_total' => 0,
                    'grand_total' => $storeGrandTotal,
                    'meta' => json_encode(['store_id' => $S['store_id']]),
                ]);

                // Create single store order for this order
                $so = StoreOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $S['store_id'],
                    'status'   => 'pending_acceptance',
                    'shipping_fee' => $storeShippingTotal,
                    'items_subtotal' => $storeItemsTotal,
                    'discount' => 0,
                    'subtotal_with_shipping' => $storeItemsTotal + $storeShippingTotal,
                ]);

                // Create order tracking
                $deliveryCode = random_int(1000, 9999);
                \App\Models\OrderTracking::create([
                    'store_order_id' => $so->id,
                    'status'         => 'pending',
                    'notes'          => 'Order placed. Waiting for store to accept.',
                    'delivery_code'  => $deliveryCode,
                ]);

                // Create order items
                foreach ($S['lines'] as $L) {
                    OrderItem::create([
                        'store_order_id' => $so->id,
                        'product_id'     => $L['product_id'],
                        'variant_id'     => $L['variant_id'] ?? null,
                        'name'           => $L['name'],
                        'sku'            => null,
                        'color'          => null,
                        'size'           => null,
                        'unit_price'     => $L['unit'],
                        'unit_discount_price' => null,
                        'qty'            => $L['qty'],
                        'line_total'     => $L['line_total'],
                    ]);

                    // Record order event for each product
                    ProductStatHelper::record($L['product_id'], 'order');
                }

                // Create chat
                Chat::firstOrCreate([
                    'store_order_id' => $so->id,
                    'user_id'        => $cart->user_id,
                    'store_id'       => $S['store_id'],
                    'type' => 'order'
                ]);

                // Send notification to this store
                $store = Store::with('user')->find($S['store_id']);
                if ($store && $store->user) {
                    UserNotificationHelper::notify(
                        $store->user->id,
                        'New Order Received',
                        "You have received a new order #{$order->order_no}. Items total: ₦" . number_format($storeItemsTotal, 2) . " (Shipping fee to be set upon acceptance)",
                        [
                            'type' => 'new_order',
                            'order_id' => $order->id,
                            'order_no' => $order->order_no,
                            'store_order_id' => $so->id,
                            'amount' => $storeItemsTotal
                        ]
                    );
                }

                $orders[] = $order->load('storeOrders.items');
            }

            // ✅ NO PAYMENT OR ESCROW AT THIS STAGE
            // Payment will be processed after store accepts the order

            $cart->update(['checked_out' => true]);
            $cart->items()->delete();

            // Notify buyer
            UserNotificationHelper::notify(
                $cart->user_id,
                'Orders Placed Successfully',
                "Your " . count($orders) . " order(s) have been placed successfully. Items total: ₦" . number_format($preview['items_total'], 2) . ". Shipping fees will be set by sellers upon order acceptance.",
                [
                    'type' => 'order_placed',
                    'order_count' => count($orders),
                    'total_amount' => $preview['items_total']
                ]
            );

            return $orders;
        });
    }

    public function paymentConfirmationWIthShoppingWallet($data)
    {
        return DB::transaction(function () use ($data) {
            $order = Order::with('storeOrders.items.product')->find($data['order_id']);
            if (!$order) {
                throw ValidationException::withMessages(['order' => 'Order not found.']);
            }
            if ($order->payment_status === 'paid') {
                throw ValidationException::withMessages(['order' => 'Order is already paid.']);
            }

            $user = Auth::user();
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'shopping_balance' => 0]
            );

            if ($wallet->shopping_balance < $data['amount']) {
                throw ValidationException::withMessages(['wallet' => 'Insufficient wallet balance.']);
            }

            // Deduct funds
            $wallet->shopping_balance -= $data['amount'];
            $wallet->save();

            // Mark order paid
            $order->payment_status = 'paid';
            $order->save();

            // Create transaction record
            $txId = $data['tx_id'] ?: 'COLTX-' . now()->format('YmdHis') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            \App\Models\Transaction::create([
                'tx_id'   => $txId,
                'amount'  => $data['amount'],
                'status'  => 'success',
                'type'    => 'order_payment',
                'order_id' => $order->id,
                'user_id' => $user->id,
            ]);

            // ✅ Lock escrow funds for each order item
            // Use the shipping fee from store order (set by seller) instead of calculating
            $storeOrder = $order->storeOrders->first();
            $storeShippingFee = $storeOrder ? (float) ($storeOrder->shipping_fee ?? 0) : 0;
            $itemsCount = $storeOrder ? $storeOrder->items->count() : 0;
            $perItemShipping = $itemsCount > 0 ? ($storeShippingFee / $itemsCount) : 0;

            foreach ($order->storeOrders as $storeOrder) {
                foreach ($storeOrder->items as $item) {
                    Escrow::create([
                        'user_id'       => $user->id,
                        'order_id'      => $order->id,
                        'store_order_id' => $storeOrder->id,
                        'order_item_id' => $item->id,
                        'amount'        => $item->line_total + $perItemShipping,
                        'status'        => 'locked',
                        'shipping_fee'  => $perItemShipping > 0 ? $perItemShipping : null,
                    ]);
                }
            }

            return $order->fresh('storeOrders.items');
        });
    }

}
