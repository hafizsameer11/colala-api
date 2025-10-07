<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTracking;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltySetting;
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
            $completedOrder = StoreOrder::with(['order.user', 'store', 'items', 'orderTracking'])
                ->where('status', 'delivered')
                ->where('store_id', $store->id)
                ->latest()
                ->paginate(20);
            $newOrders = StoreOrder::with(['order', 'store', 'items', 'orderTracking'])->whereNot('status', 'delivered')->where('store_id', $store->id)->latest()->paginate(20);
            return ResponseHelper::success([
                'completed_orders' => $completedOrder,
                'new_orders' => $newOrders
            ], "Store orders retrieved successfully");
        } catch (Exception $e) {
            return ResponseHelper::error("Something went wrong: " . $e->getMessage());
        }
    }
    public function details($storeOrderId)
    {
        try {
            $storeOrder = StoreOrder::with(['order', 'store', 'items.product.images', 'orderTracking','chat'])->where('id', $storeOrderId)->first();
            return ResponseHelper::success($storeOrder, "Store order retrieved successfully");
        } catch (Exception $e) {
            return ResponseHelper::error("Something went wrong: " . $e->getMessage());
        }
    }
    public function markOutForDelivery($orderId)
    {
        try {
            $storeOrder = StoreOrder::where('id', $orderId)->first();
            $storeOrder->update(['status' => 'out_for_delivery']);
            $orderTracking = OrderTracking::where('store_order_id', $orderId)->first();
            //if order. tracking is null, create one
            if (!$orderTracking) {
                $orderTracking = new OrderTracking();
                $deliveryCode=random_int(100000, 999999);
                $orderTracking->store_order_id = $orderId;
                $orderTracking->delivery_code = $deliveryCode;
                $orderTracking->status='out_for_delivery';
                $orderTracking->save();
            }
            $orderTracking->update(['status' => 'out_for_delivery']);

            return ResponseHelper::success($storeOrder, "Store order retrieved successfully");
        } catch (Exception $e) {
            return ResponseHelper::error("Something went wrong: " . $e->getMessage());
        }
    }
    public function verifyDeliveryCode(Request $request, $orderId)
    {
        try {
            $code = $request->code;
            $orderTracking = OrderTracking::where('store_order_id', $orderId)->first();
            if ($orderTracking->delivery_code == $code) {
                $storeOrder = StoreOrder::with('order')->where('id', $orderId)->first();
                $wasDelivered = $storeOrder && $storeOrder->status === 'delivered';
                $storeOrder->update(['status' => 'delivered']);
                $orderTracking->update(['status' => 'delivered']);

                // Award loyalty points on first-time delivery confirmation
                if (!$wasDelivered) {
                    $setting = LoyaltySetting::where('store_id', $storeOrder->store_id)->first();
                    if ($setting && $setting->enable_order_points && (int)$setting->points_per_order > 0) {
                        LoyaltyPoint::create([
                            'user_id'  => $storeOrder->order->user_id,
                            'store_id' => $storeOrder->store_id,
                            'points'   => (int)$setting->points_per_order,
                            'source'   => 'order',
                        ]);
                    }
                }
                return ResponseHelper::success($storeOrder, "Store order retrieved successfully");
            } else {
                return ResponseHelper::error("Invalid code");
            }
        } catch (Exception $e) {
            return ResponseHelper::error("Something went wrong: " . $e->getMessage());
        }
    }
}
