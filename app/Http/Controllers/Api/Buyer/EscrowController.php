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
     * GET /api/escrow
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

            // Full history (include product + order info)
            $history = Escrow::with([
                    'order:id,order_no,payment_status',
                    'orderItem:id,product_id,store_order_id,name,line_total',
                    'orderItem.product:id,name,price'
                ])
                ->where('user_id', $user->id)
                ->latest()
                ->get();

            return ResponseHelper::success([
                'locked_balance' => $lockedBalance,
                'history'        => $history
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * GET /api/escrow/history
     * Return paginated escrow history (optional convenience endpoint).
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            $history = Escrow::with([
                    'order:id,order_no,payment_status',
                    'orderItem:id,product_id,store_order_id,name,line_total',
                    'orderItem.product:id,name,price'
                ])
                ->where('user_id', $user->id)
                ->latest()
                ->paginate($request->get('per_page', 15));

            return ResponseHelper::success($history);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
