<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{Order, StoreOrder};
use App\Services\Buyer\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller {
    public function __construct(private OrderService $svc) {}

    public function list(Request $req) {
      try{
          return ResponseHelper::success($this->svc->listForUser($req->user()->id));
      }catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
         }
    }

    public function detail(Request $req, $orderId) {
        $order = Order::findOrFail($orderId);
        return ResponseHelper::success($this->svc->detailForUser($req->user()->id, $order));
    }

    public function confirmDelivered(Request $req, $storeOrderId) {
        $storeOrder = StoreOrder::findOrFail($storeOrderId);
        return ResponseHelper::success($this->svc->buyerConfirmDelivered($req->user()->id, $storeOrder));
    }

    /**
     * Check if authenticated buyer has ever ordered from a specific store
     */
    public function hasOrderedFromStore(Request $req, $storeId) {
        try {
            // Validate that storeId is numeric
            if (!is_numeric($storeId)) {
                return ResponseHelper::error('Invalid store ID. ID must be a number.', 422);
            }

            $userId = $req->user()->id;
            
            // Use a direct join query to check if user has ordered from this store
            // This is more reliable than whereHas
            $hasOrdered = StoreOrder::join('orders', 'store_orders.order_id', '=', 'orders.id')
                ->where('orders.user_id', $userId)
                ->where('store_orders.store_id', $storeId)
                ->exists();

            // Get additional info if ordered
            $orderInfo = null;
            if ($hasOrdered) {
                // Get first store order from this store
                $firstStoreOrder = StoreOrder::join('orders', 'store_orders.order_id', '=', 'orders.id')
                    ->where('orders.user_id', $userId)
                    ->where('store_orders.store_id', $storeId)
                    ->select('store_orders.*')
                    ->orderBy('store_orders.created_at', 'asc')
                    ->first();

                // Count total store orders from this store
                $totalOrders = StoreOrder::join('orders', 'store_orders.order_id', '=', 'orders.id')
                    ->where('orders.user_id', $userId)
                    ->where('store_orders.store_id', $storeId)
                    ->count();

                $orderInfo = [
                    'first_order_date' => $firstStoreOrder ? $firstStoreOrder->created_at->format('Y-m-d H:i:s') : null,
                    'total_orders' => $totalOrders,
                ];
            }

            return ResponseHelper::success([
                'has_ordered' => $hasOrdered,
                'store_id' => (int) $storeId,
                'order_info' => $orderInfo,
            ], $hasOrdered ? 'User has ordered from this store' : 'User has never ordered from this store');

        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
