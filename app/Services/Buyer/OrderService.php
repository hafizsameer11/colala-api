<?php 

namespace App\Services\Buyer;

use App\Models\{Order, StoreOrder};

class OrderService {
    public function listForUser(int $userId) {
        return Order::with(['storeOrders.store','storeOrders.items'])
            ->where('user_id',$userId)->latest()->paginate(20);
    }

    public function detailForUser(int $userId, Order $order) {
        abort_unless($order->user_id === $userId, 403);
        return $order->load(['storeOrders.store','storeOrders.items.product.images','storeOrders.items.variant','orderTracking']);
    }

    public function buyerConfirmDelivered(int $userId, StoreOrder $storeOrder) {
        abort_unless($storeOrder->order->user_id === $userId, 403);
        if (!in_array($storeOrder->status, ['out_for_delivery','delivered'])) {
            return $storeOrder;
        }
        $storeOrder->update(['status'=>'delivered']);
        return $storeOrder->fresh();
    }
}
