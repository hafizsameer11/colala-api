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
     * Return the seller's locked escrow balance and history for their products.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Get escrow funds for products from this store
            $lockedBalance = Escrow::whereHas('orderItem.product', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->where('status', 'locked')
                ->sum('amount');

            // Get escrow history for this store's products
            $history = Escrow::with([
                    'order:id,order_no,payment_status,user_id',
                    'orderItem:id,product_id,store_order_id,name,line_total',
                    'orderItem.product:id,name,price,store_id',
                    'order.user:id,full_name,email'
                ])
                ->whereHas('orderItem.product', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->latest()
                ->get();

            // Calculate total escrow released (completed orders)
            $releasedBalance = Escrow::whereHas('orderItem.product', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->where('status', 'released')
                ->sum('amount');

            // Calculate total escrow refunded
            $refundedBalance = Escrow::whereHas('orderItem.product', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->where('status', 'refunded')
                ->sum('amount');

            return ResponseHelper::success([
                'locked_balance' => (float)$lockedBalance,
                'released_balance' => (float)$releasedBalance,
                'refunded_balance' => (float)$refundedBalance,
                'total_escrow_processed' => (float)($releasedBalance + $refundedBalance),
                'history' => $history
            ], 'Seller escrow data retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/seller/escrow/history
     * Return paginated escrow history for seller's products.
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
                    'order:id,order_no,payment_status,user_id',
                    'orderItem:id,product_id,store_order_id,name,line_total',
                    'orderItem.product:id,name,price,store_id',
                    'order.user:id,full_name,email'
                ])
                ->whereHas('orderItem.product', function($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->latest()
                ->paginate($request->get('per_page', 15));

            return ResponseHelper::success($history, 'Seller escrow history retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
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
