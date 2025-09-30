<?php 


namespace App\Services;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\DB;
class TransactionService
{
public function getForUser($userId)
{
    $withrdrawals = Transaction::where('user_id', $userId)
        ->where('type', 'withdrawl')
        ->orderBy('created_at', 'desc')
        ->get();
    $deposits = Transaction::where('user_id', $userId)
        ->where('type', 'deposit')
        ->orderBy('created_at', 'desc')
        ->get();
    $orderPayments = Transaction::where('user_id', $userId)
        ->where('type', 'order_payment')
        ->orderBy('created_at', 'desc')
        ->get();
    return [
        'withdrawals' => $withrdrawals,
        'deposits' => $deposits,
        'orderPayments' => $orderPayments,
    ];
}
}