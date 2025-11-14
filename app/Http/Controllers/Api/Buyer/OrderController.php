<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{Order, Store, StoreOrder};
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
            $store = Store::findOrFail($storeId);
            $userId = $req->user()->id;
            $hasOrdered = StoreOrder::where('store_id', $storeId)
                ->whereHas('order', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->exists();
            return ResponseHelper::success($hasOrdered);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
