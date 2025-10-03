<?php

namespace App\Services\Buyer;

use App\Models\{Cart, Chat, Escrow, Order, StoreOrder, OrderItem, Wallet};
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
        $shippingTotal = 0;     // no delivery pricing → 0 shipping
        $platformFee = 0;
        $discountTotal = 0;

        foreach ($grouped as $storeId => $items) {
            $itemsSubtotal = 0;
            $lines = [];

            foreach ($items as $i) {
                $unit = $i->unit_discount_price ?? $i->unit_price ?? $i->product->discount_price ?? $i->product->price;
                $lineTotal = $unit * $i->qty;
                $itemsSubtotal += $lineTotal;
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
                'subtotal_with_shipping' => $itemsSubtotal,
                'lines' => $lines,
            ];
        }

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

    public function place(Cart $cart, array $preview): Order
    {
        return DB::transaction(function () use ($cart, $preview) {
            $order = Order::create([
                'order_no' => 'COL-' . now()->format('Ymd') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'user_id' => $cart->user_id,
                'delivery_address_id' => $preview['address_id'],
                'payment_method' => $preview['payment_method'],
                'payment_status' => 'pending',
                'items_total' => $preview['items_total'],
                'shipping_total' => $preview['shipping_total'],
                'platform_fee' => $preview['platform_fee'],
                'discount_total' => $preview['discount_total'],
                'grand_total' => $preview['grand_total'],
                'meta' => [],
            ]);

            if ($preview['payment_method'] === 'wallet') {
                $this->paymentConfirmationWIthShoppingWallet([
                    'order_id' => $order->id,
                    'amount'   => $preview['grand_total'],
                    'tx_id'    => null,
                ]);
            }

            // Create order tracking and chat


            foreach ($preview['stores'] as $S) {
                $so = StoreOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $S['store_id'],
                    'status'   => 'placed',
                    'shipping_fee' => 0,
                    'items_subtotal' => $S['items_subtotal'],
                    'discount' => 0,
                    'subtotal_with_shipping' => $S['items_subtotal'],
                ]);

                // ✅ tracking per store order
                $deliveryCode = random_int(1000, 9999);
                \App\Models\OrderTracking::create([
                    'store_order_id' => $so->id,   // now linked correctly
                    'status'         => 'pending',
                    'notes'          => 'Order has been placed and is pending processing.',
                    'delivery_code'  => $deliveryCode,
                ]);

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
                }

                Chat::firstOrCreate([
                    'store_order_id' => $so->id,
                    'user_id'        => $cart->user_id,
                    'store_id'       => $S['store_id'],
                ]);
            }


            $cart->update(['checked_out' => true]);
            $cart->items()->delete();

            return $order->load('storeOrders.items');
        });
    }

    public function paymentConfirmationWIthShoppingWallet($data)
    {
        return DB::transaction(function () use ($data) {
            $order = Order::with('storeOrders.items')->find($data['order_id']);
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
            foreach ($order->storeOrders as $storeOrder) {
                foreach ($storeOrder->items as $item) {
                    Escrow::create([
                        'user_id'       => $user->id,
                        'order_id'      => $order->id,
                        'order_item_id' => $item->id,
                        'amount'        => $item->line_total,
                        'status'        => 'locked',
                    ]);
                }
            }

            return $order->fresh('storeOrders.items');
        });
    }
}
