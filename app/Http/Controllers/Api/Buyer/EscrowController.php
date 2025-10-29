<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Escrow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class EscrowController extends Controller
{
    /**
     * GET /api/buyer/escrow
     * Return the user's locked balance and full escrow history.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Total locked balance
            $lockedBalance = Escrow::where('user_id', $user->id)
                ->where('status', 'locked')
                ->sum('amount');

            // Full history (now using store_order_id instead of order_item_id)
            $history = Escrow::with([
                    'order:id,order_no,payment_status,status',
                    'storeOrderg',
                    'storeOrder.store',
                    'storeOrder.items',
                    'storeOrder.items.product',
                    'storeOrder.items.product.images'
                ])
                ->where('user_id', $user->id)
                ->latest()
                ->get();
               

            return ResponseHelper::success([
                'locked_balance' => $lockedBalance,
                'history'        => $history
            ]);
        } catch (Exception $e) {
            Log::error('Escrow index error: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * GET /api/buyer/escrow/history
     * Return paginated escrow history (optional convenience endpoint).
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            $history = Escrow::with([
                    'order:id,order_no,payment_status,status',
                    'storeOrder',
                    'storeOrder.store',
                    'storeOrder.items',
                    'storeOrder.items.product',
                    'storeOrder.items.product.images'
                ])
                ->where('user_id', $user->id)
                ->latest()
                ->paginate($request->get('per_page', 15));

            // Transform the paginated results
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
                    'store' => $escrow->storeOrder && $escrow->storeOrder->store ? [
                        'id' => $escrow->storeOrder->store->id,
                        'store_name' => $escrow->storeOrder->store->store_name,
                        'profile_image' => $escrow->storeOrder->store->profile_image 
                            ? asset('storage/' . $escrow->storeOrder->store->profile_image) 
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
                                    ? asset('storage/' . $item->product->images->first()->url)
                                    : null,
                            ];
                        })->toArray()
                        : [],
                ];
            });

            return ResponseHelper::success($history);
        } catch (Exception $e) {
            Log::error('Escrow history error: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
