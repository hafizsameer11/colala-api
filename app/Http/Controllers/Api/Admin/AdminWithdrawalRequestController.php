<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminWithdrawalRequestController extends Controller
{
    /**
     * List withdrawal requests with optional filters
     */
    public function index(Request $request)
    {
        try {
            $query = WithdrawalRequest::with('user')
                ->orderByDesc('created_at');

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('account_name', 'like', "%{$search}%")
                      ->orWhere('account_number', 'like', "%{$search}%")
                      ->orWhere('bank_name', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%")
                                   ->orWhere('phone', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $perPage = (int) $request->get('per_page', 20);
            $requests = $query->paginate($perPage);

            // Format the response
            $requests->getCollection()->transform(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'user' => $withdrawal->user ? [
                        'id' => $withdrawal->user->id,
                        'full_name' => $withdrawal->user->full_name,
                        'email' => $withdrawal->user->email,
                        'phone' => $withdrawal->user->phone,
                    ] : null,
                    'amount' => (float) $withdrawal->amount,
                    'bank_name' => $withdrawal->bank_name,
                    'account_number' => $withdrawal->account_number,
                    'account_name' => $withdrawal->account_name,
                    'status' => $withdrawal->status,
                    'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $withdrawal->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return ResponseHelper::success($requests);
        } catch (Exception $e) {
            Log::error('AdminWithdrawalRequestController@index: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get single withdrawal request details
     */
    public function show($id)
    {
        try {
            $withdrawal = WithdrawalRequest::with('user')->findOrFail($id);

            // Get related transaction
            $transaction = Transaction::where('user_id', $withdrawal->user_id)
                ->where('amount', $withdrawal->amount)
                ->where('type', 'like', '%withdraw%')
                ->whereDate('created_at', $withdrawal->created_at->toDateString())
                ->first();

            return ResponseHelper::success([
                'id' => $withdrawal->id,
                'user' => $withdrawal->user ? [
                    'id' => $withdrawal->user->id,
                    'full_name' => $withdrawal->user->full_name,
                    'email' => $withdrawal->user->email,
                    'phone' => $withdrawal->user->phone,
                ] : null,
                'amount' => (float) $withdrawal->amount,
                'bank_name' => $withdrawal->bank_name,
                'account_number' => $withdrawal->account_number,
                'account_name' => $withdrawal->account_name,
                'status' => $withdrawal->status,
                'transaction' => $transaction ? [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                ] : null,
                'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $withdrawal->updated_at->format('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            Log::error('AdminWithdrawalRequestController@show: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 404);
        }
    }

    /**
     * Approve withdrawal request
     */
    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $withdrawal = WithdrawalRequest::findOrFail($id);

            if ($withdrawal->status !== 'pending') {
                return ResponseHelper::error('Withdrawal request is not pending. Current status: ' . $withdrawal->status, 422);
            }

            // Update withdrawal request status
            $withdrawal->status = 'approved';
            $withdrawal->save();

            // Update related transaction status to completed
            $transaction = Transaction::where('user_id', $withdrawal->user_id)
                ->where('amount', $withdrawal->amount)
                ->where('type', 'like', '%withdraw%')
                ->whereDate('created_at', $withdrawal->created_at->toDateString())
                ->where('status', 'pending')
                ->first();

            if ($transaction) {
                $transaction->status = 'completed';
                $transaction->save();
            }

            DB::commit();

            return ResponseHelper::success([
                'id' => $withdrawal->id,
                'status' => $withdrawal->status,
                'message' => 'Withdrawal request approved successfully'
            ], 'Withdrawal request approved successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('AdminWithdrawalRequestController@approve: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Reject withdrawal request
     */
    public function reject(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $withdrawal = WithdrawalRequest::findOrFail($id);

            if ($withdrawal->status !== 'pending') {
                return ResponseHelper::error('Withdrawal request is not pending. Current status: ' . $withdrawal->status, 422);
            }

            // Get user and wallet
            $user = User::findOrFail($withdrawal->user_id);
            $wallet = $user->wallet;

            if (!$wallet) {
                return ResponseHelper::error('User wallet not found', 404);
            }

            // Find related transaction to determine which balance to refund
            $transaction = Transaction::where('user_id', $withdrawal->user_id)
                ->where('amount', $withdrawal->amount)
                ->where('type', 'like', '%withdraw%')
                ->whereDate('created_at', $withdrawal->created_at->toDateString())
                ->where('status', 'pending')
                ->first();

            // Refund the amount back to the appropriate balance
            if ($transaction && $transaction->type === 'withdrawal_referral') {
                // Refund to referral balance
                $wallet->increment('referral_balance', $withdrawal->amount);
                $refundedTo = 'referral_balance';
            } else {
                // Refund to shopping balance (default)
                $wallet->increment('shopping_balance', $withdrawal->amount);
                $refundedTo = 'shopping_balance';
            }

            // Update withdrawal request status
            $withdrawal->status = 'rejected';
            $withdrawal->save();

            // Update related transaction status to failed
            if ($transaction) {
                $transaction->status = 'failed';
                $transaction->save();
            }

            DB::commit();

            return ResponseHelper::success([
                'id' => $withdrawal->id,
                'status' => $withdrawal->status,
                'refunded_amount' => (float) $withdrawal->amount,
                'refunded_to' => $refundedTo,
                'message' => 'Withdrawal request rejected and amount refunded'
            ], 'Withdrawal request rejected successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('AdminWithdrawalRequestController@reject: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

