<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{WithdrawalRequest, Wallet, Transaction};
use App\Services\FlutterwaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
     * Handle referral balance withdrawal request
     */
    public function requestReferralWithdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:1',
            'bank_name'      => 'required|string',
            'account_number' => 'required|string',
            'account_name'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error($validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            $user   = $request->user();
            $wallet = $user->wallet()->lockForUpdate()->first();

            if (!$wallet || $wallet->referral_balance < $data['amount']) {
                return ResponseHelper::error('Insufficient referral balance.', 422);
            }

            $wallet->decrement('referral_balance', $data['amount']);

            $txId = 'WD-REF-' . now()->format('YmdHis') . '-' . random_int(100000, 999999);
            Transaction::create([
                'tx_id'   => $txId,
                'amount'  => $data['amount'],
                'status'  => 'pending',
                'type'    => 'withdrawal_referral',
                'order_id'=> null,
                'user_id' => $user->id,
            ]);

            WithdrawalRequest::create([
                'user_id'       => $user->id,
                'amount'        => $data['amount'],
                'bank_name'     => $data['bank_name'],
                'account_number'=> $data['account_number'],
                'account_name'  => $data['account_name'],
                'status'        => 'pending'
            ]);

            DB::commit();
            return ResponseHelper::success(null, 'Referral withdrawal request submitted successfully.');
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

    /**
     * Get list of banks from Flutterwave
     * STEP 1: Frontend calls this to get bank list for dropdown
     */
    public function getBanks(Request $request, FlutterwaveService $fw)
    {
        try {
            $country = $request->get('country', 'NG');
            $banks = $fw->getBanks($country);
            
            if (($banks['status'] ?? '') === 'success') {
                return ResponseHelper::success($banks['data'] ?? []);
            }
            
            return ResponseHelper::error('Failed to fetch banks', 500);
        } catch (Exception $e) {
            Log::error('GetBanks Error: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Validate bank account number
     * STEP 2: Frontend sends bank_code and account_number for validation
     * Returns account name if valid
     */
    public function validateAccount(Request $request, FlutterwaveService $fw)
    {
        $data = $request->validate([
            "bank_code" => "required|string",
            "account_number" => "required|string|min:10|max:12",
        ]);

        try {
            $resolve = $fw->resolveAccount($data["account_number"], $data["bank_code"]);

            if (($resolve["status"] ?? "") !== "success") {
                return ResponseHelper::error(
                    $resolve["message"] ?? "Invalid bank account details",
                    422
                );
            }

            return ResponseHelper::success([
                "account_name" => $resolve["data"]["account_name"] ?? "",
                "account_number" => $data["account_number"],
                "bank_code" => $data["bank_code"],
                "valid" => true
            ], "Account validated successfully");
        } catch (Exception $e) {
            Log::error('ValidateAccount Error: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Automatic withdrawal using Flutterwave
     * STEP 3: After validation, user submits amount and initiates withdrawal
     */
    public function automaticWithdraw(Request $request, FlutterwaveService $fw)
    {
        $data = $request->validate([
            "bank_code" => "required|string",
            "bank_name" => "required|string",
            "account_number" => "required|string|min:10|max:12",
            "account_name" => "required|string", // Should come from validation step
            "amount" => "required|numeric|min:100"
        ]);

        DB::beginTransaction();
        try {
            $user = $request->user();
            $wallet = $user->wallet()->lockForUpdate()->first();

            if (!$wallet || $wallet->shopping_balance < $data['amount']) {
                DB::rollBack();
                return ResponseHelper::error('Insufficient wallet balance.', 422);
            }

            // Optional: Re-validate account (for security)
            $resolve = $fw->resolveAccount($data["account_number"], $data["bank_code"]);
            
            if (($resolve["status"] ?? "") !== "success") {
                DB::rollBack();
                return ResponseHelper::error(
                    $resolve["message"] ?? "Invalid bank account details",
                    422
                );
            }

            $accountName = $resolve["data"]["account_name"] ?? $data["account_name"];

            // STEP 2 — GENERATE UNIQUE REFERENCE
            $reference = "payout-" . Str::uuid();

            // STEP 3 — DEDUCT FROM WALLET
            $wallet->decrement('shopping_balance', $data['amount']);

            // STEP 4 — CREATE TRANSACTION
            $txId = 'WD-FW-' . now()->format('YmdHis') . '-' . random_int(100000, 999999);
            Transaction::create([
                'tx_id'   => $txId,
                'amount'  => $data['amount'],
                'status'  => 'pending',
                'type'    => 'withdrawl',
                'order_id'=> null,
                'user_id' => $user->id,
            ]);

            // STEP 5 — INITIATE TRANSFER
            $transfer = $fw->makeTransfer(
                $data["bank_code"],
                $data["account_number"],
                $data["amount"],
                $reference
            );

            // STEP 6 — SAVE PAYOUT
            $withdrawal = WithdrawalRequest::create([
                "user_id" => $user->id,
                "bank_code" => $data["bank_code"],
                "bank_name" => $data["bank_name"],
                "account_number" => $data["account_number"],
                "account_name" => $accountName,
                "amount" => $data["amount"],
                "reference" => $reference,
                "flutterwave_transfer_id" => $transfer["data"]["id"] ?? null,
                "status" => ($transfer["status"] ?? "") === "success" ? "pending" : "pending",
                "remarks" => $transfer["message"] ?? null
            ]);

            DB::commit();

            return ResponseHelper::success([
                "withdrawal" => $withdrawal,
                "reference" => $reference,
                "flutterwave_response" => $transfer
            ], "Withdrawal initiated successfully");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('AutomaticWithdraw Error: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
