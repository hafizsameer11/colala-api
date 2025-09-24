<?php


namespace App\Services\Buyer;

use App\Models\{Cart, Order, StoreOrder, OrderItem, StoreDeliveryPricing, Wallet};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function preview(Cart $cart, int $addressId, array $deliveryPricingIds, string $paymentMethod): array
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
                $lines[] = [
                    'product_id' => $i->product_id,
                    'variant_id' => $i->variant_id,
                    'name' => $i->product->name,
                    'qty' => $i->qty,
                    'unit' => $unit,
                    'line_total' => $lineTotal
                ];
            }

            $deliveryId = $deliveryPricingIds[$storeId] ?? null;
            if (!$deliveryId) {
                throw ValidationException::withMessages(['delivery' => "Missing delivery method for store {$storeId}."]);
            }
            $delivery = StoreDeliveryPricing::where('store_id', $storeId)->findOrFail($deliveryId);
            $shipFee = (float)$delivery->price;

            $itemsTotal += $itemsSubtotal;
            $shippingTotal += $shipFee;

            $summaryStores[] = [
                'store_id' => $storeId,
                'delivery_pricing_id' => $delivery->id,
                'shipping_fee' => $shipFee,
                'items_subtotal' => $itemsSubtotal,
                'subtotal_with_shipping' => $itemsSubtotal + $shipFee,
                'lines' => $lines
            ];
        }

        $platformFee = round($itemsTotal * 0.015, 2); // 1.5%
        $grand = $itemsTotal + $shippingTotal + $platformFee - $discountTotal;

        return [
            'address_id' => $addressId,
            'payment_method' => $paymentMethod,
            'items_total' => $itemsTotal,
            'shipping_total' => $shippingTotal,
            'platform_fee' => $platformFee,
            'discount_total' => $discountTotal,
            'grand_total' => $grand,
            'stores' => $summaryStores
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
            if($preview['payment_method']==='wallet'){
                $this->paymentConfirmationWIthShoppingWallet([
                    'order_id'=>$order->id,
                    'amount'=>$preview['grand_total'],
                    'tx_id'=>null
                ]);
            }
            //create order tracking for that 
            $deliveryCode = random_int(1000, 9999);
            $orderTracking = \App\Models\OrderTracking::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'notes' => 'Order has been placed and is pending processing.',
                'delivery_code' => $deliveryCode,
            ]);
            foreach ($preview['stores'] as $S) {
                $so = StoreOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $S['store_id'],
                    'status' => 'placed',
                    'delivery_pricing_id' => $S['delivery_pricing_id'],
                    'shipping_fee' => $S['shipping_fee'],
                    'items_subtotal' => $S['items_subtotal'],
                    'discount' => 0,
                    'subtotal_with_shipping' => $S['subtotal_with_shipping'],
                ]);

                foreach ($S['lines'] as $L) {
                    OrderItem::create([
                        'store_order_id' => $so->id,
                        'product_id' => $L['product_id'],
                        'variant_id' => $L['variant_id'] ?? null,
                        'name' => $L['name'],
                        'sku' => null,
                        'color' => null,
                        'size' => null,
                        'unit_price' => $L['unit'],
                        'unit_discount_price' => null,
                        'qty' => $L['qty'],
                        'line_total' => $L['line_total'],
                    ]);
                }
            }

            $cart->update(['checked_out' => true]);
            $cart->items()->delete();

            return $order->load('storeOrders.items');
        });
    }
    public function paymentConfirmationWIthShoppingWallet($data){
        //check if user have wallet not than create and check shopping_balance
        //if have balance than deduct balance and update order payment status to paid
        //if not enough balance than throw error
        //record transaction
        return DB::transaction(function () use ($data) {
            $order = Order::find($data['order_id']);
            if (!$order) {
                throw ValidationException::withMessages(['order' => 'Order not found.']);
            }
            if ($order->payment_status === 'paid') {
                throw ValidationException::withMessages(['order' => 'Order is already paid.']);
            }
            // Assuming User model has shopping_balance field
            // $user = $order->user;
            $user=Auth::user();
            // $user
            $wallet=Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'shopping_balance' => 0]
            );
            if ($user->shopping_balance < $data['amount']) {
                throw ValidationException::withMessages(['wallet' => 'Insufficient wallet balance.']);
            }
            // Deduct balance
            $wallet->shopping_balance -= $data['amount'];
            $wallet->save();
            // Update order status
            $order->payment_status = 'paid';
            $order->save();
            // Record transaction
            //generate a tx id wit colala prefix
            $txId = 'COLTX-' . now()->format('YmdHis') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            \App\Models\Transaction::create([
                'tx_id' => $txId,
                'amount' => $data['amount'],
                'status' => 'success',
                'type' => 'wallet',
                'order_id' => $order->id,
                'user_id' => $user->id,
            ]);
            return $order->load('storeOrders.items');
        });


    }
}
