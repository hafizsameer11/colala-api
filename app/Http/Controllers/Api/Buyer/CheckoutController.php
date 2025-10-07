<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CheckoutPreviewRequest;
use App\Http\Requests\PaymentConfirmationReques;
use App\Models\Order;
use App\Models\Transaction;
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
            $order = $this->chk->place($cart, $preview);
            return ResponseHelper::success($order);
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

            $order = Order::with('storeOrders.items')->find($orderId);
            if (!$order) {
                return ResponseHelper::error('Order not found', 404);
            }

            if ($order->payment_status === 'paid') {
                return ResponseHelper::error('Order is already paid', 400);
            }

            $order->payment_status = 'paid';
            $order->save();

            Transaction::create([
                'tx_id' => $txId,
                'amount' => $amount,
                'status' => 'completed',
                'type' => 'order_payment',
                'order_id' => $orderId,
                'user_id' => $req->user()->id,
            ]);

            // ✅ Lock escrow funds for each order item
            foreach ($order->storeOrders as $storeOrder) {
                foreach ($storeOrder->items as $item) {
                    \App\Models\Escrow::create([
                        'user_id'       => $req->user()->id,
                        'order_id'      => $order->id,
                        'order_item_id' => $item->id,
                        'amount'        => $item->line_total,
                        'status'        => 'locked',
                    ]);
                }
            }

            return ResponseHelper::success(['message' => 'Payment confirmed successfully.']);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
