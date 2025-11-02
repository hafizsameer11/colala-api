<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Escrow;
use App\Models\Store;
use App\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class SellerEscrowController extends Controller
{
    /**
     * GET /api/seller/escrow
     * Return the seller's locked escrow balance and full escrow history.
     * Matches the buyer escrow format for consistency.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Get escrow funds locked for this store (using store_order_id)
            $lockedBalance = Escrow::whereHas('storeOrder', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->where('status', 'locked')
                ->sum('amount');

            // Full history with all related data (matching buyer format)
            $history = Escrow::with([
                    'order:id,order_no,payment_status,status,user_id',
                    'order.user:id,full_name,email,phone,profile_picture',
                    'storeOrder',
                    'storeOrder.store',
                    'storeOrder.items',
                    'storeOrder.items.product',
                    'storeOrder.items.product.images'
                ])
                ->whereHas('storeOrder', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->latest()
                ->get();

            return ResponseHelper::success([
                'locked_balance' => (float)$lockedBalance,
                'history' => $history
            ]);
        } catch (Exception $e) {
            Log::error('Seller escrow index error: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * GET /api/seller/escrow/history
     * Return paginated escrow history (matching buyer format).
     */
    public function history(Request $request)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            $history = Escrow::with([
                    'order:id,order_no,payment_status,status,user_id',
                    'order.user:id,full_name,email,phone,profile_picture',
                    'storeOrder',
                    'storeOrder.store',
                    'storeOrder.items',
                    'storeOrder.items.product',
                    'storeOrder.items.product.images'
                ])
                ->whereHas('storeOrder', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->latest()
                ->paginate($request->get('per_page', 15));

            // Transform the paginated results (matching buyer format)
            $history->getCollection()->transform(function ($escrow) {
                return [
                    'id' => $escrow->id,
                    'amount' => $escrow->amount,
                    'shipping_fee' => $escrow->shipping_fee,
                    'status' => $escrow->status,
                    'created_at' => $escrow->created_at,
                    'order' => [
                        'id' => $escrow->order->id ?? null,
                        'order_no' => $escrow->order->order_no ?? null,
                        'payment_status' => $escrow->order->payment_status ?? null,
                        'status' => $escrow->order->status ?? null,
                    ],
                    'buyer' => $escrow->order && $escrow->order->user ? [
                        'id' => $escrow->order->user->id,
                        'full_name' => $escrow->order->user->full_name,
                        'email' => $escrow->order->user->email,
                        'phone' => $escrow->order->user->phone,
                        'profile_picture' => $escrow->order->user->profile_picture 
                            ? asset('storage/' . $escrow->order->user->profile_picture) 
                            : null,
                    ] : null,
                    'store_order' => $escrow->storeOrder ? [
                        'id' => $escrow->storeOrder->id,
                        'status' => $escrow->storeOrder->status,
                        'items_subtotal' => $escrow->storeOrder->items_subtotal,
                        'shipping_fee' => $escrow->storeOrder->shipping_fee,
                        'total' => $escrow->storeOrder->subtotal_with_shipping,
                    ] : null,
                    'items' => $escrow->storeOrder && $escrow->storeOrder->items 
                        ? $escrow->storeOrder->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->name,
                                'qty' => $item->qty,
                                'unit_price' => $item->unit_price,
                                'line_total' => $item->line_total,
                                'product_image' => $item->product && $item->product->images->isNotEmpty()
                                    ? asset('storage/' . $item->product->images->first()->path)
                                    : null,
                            ];
                        })->toArray()
                        : [],
                ];
            });

            return ResponseHelper::success($history);
        } catch (Exception $e) {
            Log::error('Seller escrow history error: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * GET /api/seller/escrow/orders
     * Return escrow details grouped by orders for better overview.
     */
    public function orders(Request $request)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Get store orders with their escrow details
            $orders = StoreOrder::with([
                    'order:id,order_no,payment_status,user_id,created_at',
                    'order.user:id,full_name,email',
                    'items:id,store_order_id,product_id,name,line_total',
                    'items.product:id,name,price'
                ])
                ->where('store_id', $store->id)
                ->whereHas('order.escrows')
                ->latest()
                ->paginate($request->get('per_page', 15));

            // Add escrow summary for each order
            $orders->getCollection()->transform(function ($storeOrder) {
                $escrowSummary = Escrow::where('order_id', $storeOrder->order_id)
                    ->whereHas('orderItem.product', function($query) use ($storeOrder) {
                        $query->where('store_id', $storeOrder->store_id);
                    })
                    ->selectRaw('status, SUM(amount) as total_amount')
                    ->groupBy('status')
                    ->get()
                    ->keyBy('status');

                $storeOrder->escrow_summary = [
                    'locked' => (float)($escrowSummary->get('locked')->total_amount ?? 0),
                    'released' => (float)($escrowSummary->get('released')->total_amount ?? 0),
                    'refunded' => (float)($escrowSummary->get('refunded')->total_amount ?? 0),
                ];

                return $storeOrder;
            });

            return ResponseHelper::success($orders, 'Seller escrow orders retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
