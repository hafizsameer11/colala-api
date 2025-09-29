<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReferralTransferRequest;
use App\Http\Requests\ReferralWithdrawRequest;
use App\Models\{ReferralTransfer, ReferralWithdrawal, ReferralFaq, Wallet};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ReferralController extends Controller
{
    public function summary(Request $request)
    {
        try {
            $user = $request->user();
            $earning = $user->referralEarning;

            $data = [
                'total_earned'    => $earning->total_earned ?? 0,
                'total_withdrawn' => $earning->total_withdrawn ?? 0,
                'current_balance' => $earning->current_balance ?? 0,
                'no_of_referrals' => $user->referrals()->count(),
                'referral_code'   => $user->referral_code,
            ];

            return ResponseHelper::success($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function transfer(ReferralTransferRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();
            $earning = $user->referralEarning;

            if ($earning->current_balance < $request->amount) {
                return ResponseHelper::error('Insufficient balance', 422);
            }

            $earning->decrement('current_balance', $request->amount);

            ReferralTransfer::create([
                'user_id' => $user->id,
                'amount'  => $request->amount,
                'status'  => 'completed'
            ]);
             $wallet=Wallet::where('user_id',$user->id)->first();
            $wallet->shopping_balance += $request->amount;
            $wallet->save();

            DB::commit();
            return ResponseHelper::success(null, "Transferred ₦{$request->amount} to shopping wallet.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function withdraw(ReferralWithdrawRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();
            $earning = $user->referralEarning;

            if ($earning->current_balance < $request->amount) {
                return ResponseHelper::error('Insufficient balance', 422);
            }

            $earning->decrement('current_balance', $request->amount);
            $earning->increment('total_withdrawn', $request->amount);

            ReferralWithdrawal::create([
                'user_id'       => $user->id,
                'amount'        => $request->amount,
                'bank_name'     => $request->bank_name,
                'account_number'=> $request->account_number,
                'account_name'  => $request->account_name,
                'status'        => 'pending'
            ]);
           
            // $wallet->increment('shopping_balance',$request->amount);

            

            DB::commit();
            return ResponseHelper::success(null, 'Withdrawal request submitted.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function faqs()
    {
        try {
            return ResponseHelper::success(
                ReferralFaq::where('is_active', true)->get()
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function history(Request $request)
    {
        try {
            $user = $request->user();
            $data = [
                'referrals'  => $user->referrals()->latest()->get(),
                'transfers'  => $user->referralTransfers()->latest()->get(),
                'withdrawals'=> $user->referralWithdrawals()->latest()->get()
            ];
            return ResponseHelper::success($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
