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

    public function detail(Request $req, Order $order) {
        return ResponseHelper::success($this->svc->detailForUser($req->user()->id, $order));
    }

    public function confirmDelivered(Request $req, StoreOrder $storeOrder) {
        return ResponseHelper::success($this->svc->buyerConfirmDelivered($req->user()->id, $storeOrder));
    }
}
