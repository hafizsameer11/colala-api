<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreOrder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerOrderController extends Controller
{
    public function userStore()
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        return $store;
    }
    public function index(Request $request)
    {
        try {
            $store = $this->userStore();

            if (!$store) {
                return ResponseHelper::error("No store found for this user.");
            }

            // ✅ Fetch store orders (each belongs to an order)
            $completedOrder = StoreOrder::with(['order', 'store', 'items'])
                ->where('status', 'delivered')
                ->where('store_id', $store->id)
                ->latest()
                ->paginate(20);
            $newOrders = StoreOrder::with(['order', 'store', 'items'])->whereNot('status', 'delivered')->where('store_id', $store->id)->latest()->paginate(20);
            return ResponseHelper::success([
                'completed_orders' => $completedOrder,
                'new_orders' => $newOrders
            ], "Store orders retrieved successfully");
        } catch (Exception $e) {
            return ResponseHelper::error("Something went wrong: " . $e->getMessage());
        }
    }
    public function details($storeOrderId){
        try {
            $storeOrder = StoreOrder::with(['order', 'store', 'items'])->where('id', $storeOrderId)->first();
            return ResponseHelper::success($storeOrder, "Store order retrieved successfully");
        } catch (Exception $e) {
            return ResponseHelper::error("Something went wrong: " . $e->getMessage());
        }
    }
}
