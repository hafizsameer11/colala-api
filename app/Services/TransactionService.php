<?php 


namespace App\Services;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\DB;
class TransactionService
{
public function getForUser($userId)
{
    // Get withdrawals with withdrawal request details
    $withdrawals = Transaction::where('user_id', $userId)
        ->where('type', 'withdrawl')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($transaction) {
            // Find matching withdrawal request by user_id, amount, and created within 5 minutes
            // This matches the transaction with the withdrawal request created around the same time
            $withdrawalRequest = null;
            
            if ($transaction->created_at) {
                $transactionDate = $transaction->created_at;
                $withdrawalRequest = WithdrawalRequest::where('user_id', $transaction->user_id)
                    ->where('amount', $transaction->amount)
                    ->whereBetween('created_at', [
                        $transactionDate->copy()->subMinutes(5),
                        $transactionDate->copy()->addMinutes(5)
                    ])
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                // If not found with time window, try matching by date only (fallback)
                if (!$withdrawalRequest) {
                    $withdrawalRequest = WithdrawalRequest::where('user_id', $transaction->user_id)
                        ->where('amount', $transaction->amount)
                        ->whereDate('created_at', $transactionDate->toDateString())
                        ->orderBy('created_at', 'desc')
                        ->first();
                }
            } else {
                // If transaction has no created_at, try matching by user_id and amount only
                $withdrawalRequest = WithdrawalRequest::where('user_id', $transaction->user_id)
                    ->where('amount', $transaction->amount)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
            
            // Add withdrawal request details to transaction
            $transaction->withdrawal_request = $withdrawalRequest;
            
            return $transaction;
        });
    
    $deposits = Transaction::where('user_id', $userId)
        ->where('type', 'deposit')
        ->orderBy('created_at', 'desc')
        ->get();
    $orderPayments = Transaction::where('user_id', $userId)
        ->where('type', 'order_payment')->with('order')
        ->orderBy('created_at', 'desc')
        ->get();
        $wallet=Wallet::where('user_id',$userId)->first();

    return [
        'balance'=>$wallet ? $wallet->shopping_balance : 0,
        'withdrawals' => $withdrawals,
        'deposits' => $deposits,
        'orderPayments' => $orderPayments,
    ];
}
}