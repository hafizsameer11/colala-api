<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{WithdrawalRequest, Wallet, Transaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class WalletWithdrawalController extends Controller
{
    /**
     * Handle withdrawal request
     */
    public function requestWithdraw(Request $request)
    {
        // ✅ Use manual validator so we control the JSON response
        $validator = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:1',
            'bank_name'      => 'required|string',
            'account_number' => 'required|string',
            'account_name'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(
                $validator->errors()->first(), // short message
                422
            );
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            $user   = $request->user();
            $wallet = $user->wallet()->lockForUpdate()->first();

            if (!$wallet || $wallet->shopping_balance < $data['amount']) {
                return ResponseHelper::error('Insufficient wallet balance.', 422);
            }

            // Deduct from shopping balance immediately
            $wallet->decrement('shopping_balance', $data['amount']);

            // Create transaction (without order)
            $txId = 'WD-' . now()->format('YmdHis') . '-' . random_int(100000, 999999);
            Transaction::create([
                'tx_id'   => $txId,
                'amount'  => $data['amount'],
                'status'  => 'pending',
                'type'    => 'withdrawl',
                'order_id'=> null,
                'user_id' => $user->id,
            ]);

            // Create withdrawal request record
            WithdrawalRequest::create([
                'user_id'       => $user->id,
                'amount'        => $data['amount'],
                'bank_name'     => $data['bank_name'],
                'account_number'=> $data['account_number'],
                'account_name'  => $data['account_name'],
                'status'        => 'pending'
            ]);

            DB::commit();
            return ResponseHelper::success(null, 'Withdrawal request submitted successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Get current user's withdrawal requests
     */
    public function myWithdrawals(Request $request)
    {
        try {
            $withdrawals = WithdrawalRequest::where('user_id', $request->user()->id)
                ->latest()
                ->get();

            return ResponseHelper::success($withdrawals);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
