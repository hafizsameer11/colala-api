<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTracking;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltySetting;
use App\Models\Wallet;
use App\Models\User;
use App\Models\StoreReferralEarning;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\StoreUser;
use App\Services\EscrowService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SellerOrderController extends Controller
{
    protected EscrowService $escrowService;

    public function __construct(EscrowService $escrowService)
    {
        $this->escrowService = $escrowService;
    }
    public function userStore()
    {
        $user = Auth::user();

        $store = Store::where('user_id', $user->id)->first();
        if(!$store){
            $storeUser = StoreUser::where('user_id', $user->id)->first();
            if($storeUser){
                $store = $storeUser->store;
            }
        }
        if(!$store){
            throw new Exception('Store not found');
        }
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
            $newOrders = StoreOrder::with(['order.user', 'store', 'items', 'orderTracking'])->whereNot('status', 'delivered')->where('store_id', $store->id)->latest()->paginate(20);
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
            $storeOrder = StoreOrder::with(['order.user', 'store', 'items.product.images', 'orderTracking','chat','order.deliveryAddress'])->where('id', $storeOrderId)->first();
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

                // Award loyalty points and unlock escrow on first-time delivery confirmation
                if (!$wasDelivered) {
                    // Unlock escrow funds and transfer to seller
                    $this->escrowService->releaseForStoreOrder($storeOrder, null, 'Seller delivery code verification');
                    $setting = LoyaltySetting::where('store_id', $storeOrder->store_id)->first();
                    if ($setting && $setting->enable_order_points && (int)$setting->points_per_order > 0) {
                        $points = (int)$setting->points_per_order;
                        LoyaltyPoint::create([
                            'user_id'  => $storeOrder->order->user_id,
                            'store_id' => $storeOrder->store_id,
                            'points'   => $points,
                            'source'   => 'order',
                        ]);

                        // Update or create wallet loyalty points balance
                        $wallet = Wallet::firstOrCreate(
                            ['user_id' => $storeOrder->order->user_id],
                            ['shopping_balance' => 0, 'reward_balance' => 0, 'referral_balance' => 0, 'loyality_points' => 0]
                        );
                        $wallet->increment('loyality_points', $points);
                    }

                    // Referral bonus: if buyer was invited, award referrer
                    $buyer = User::find($storeOrder->order->user_id);
                    $inviteCode = $buyer?->invite_code;
                    if ($inviteCode) {
                        $referrer = User::where('user_code', $inviteCode)->first();
                        if ($referrer && $setting && $setting->enable_referral_points && (int)$setting->points_per_referral > 0) {
                            $refPoints = (int)$setting->points_per_referral;

                            // Record store referral earning
                            StoreReferralEarning::create([
                                'user_id'  => $referrer->id,
                                'store_id' => $storeOrder->store_id,
                                'order_id' => $storeOrder->order_id,
                                'amount'   => $refPoints,
                            ]);

                            // Update or create wallet referral balance (not loyalty)
                            $refWallet = Wallet::firstOrCreate(
                                ['user_id' => $referrer->id],
                                ['shopping_balance' => 0, 'reward_balance' => 0, 'referral_balance' => 0, 'loyality_points' => 0]
                            );
                            $refWallet->increment('referral_balance', $refPoints);
                        }
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
