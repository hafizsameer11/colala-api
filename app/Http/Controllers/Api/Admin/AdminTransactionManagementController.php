<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTransactionManagementController extends Controller
{
    /**
     * Get all transactions with filtering and pagination
     */
    public function getAllTransactions(Request $request)
    {
        try {
            $query = Transaction::with(['user', 'order']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            if ($request->has('date_range')) {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                        break;
                }
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tx_id', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $transactions = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_transactions' => Transaction::count(),
                'pending_transactions' => Transaction::where('status', 'pending')->count(),
                'successful_transactions' => Transaction::where('status', 'successful')->count(),
                'failed_transactions' => Transaction::where('status', 'failed')->count(),
                'total_amount' => Transaction::where('status', 'successful')->sum('amount'),
            ];

            return ResponseHelper::success([
                'transactions' => $this->formatTransactionsData($transactions),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed transaction information
     */
    public function getTransactionDetails($transactionId)
    {
        try {
            $transaction = Transaction::with(['user', 'order.storeOrder.store.user'])->findOrFail($transactionId);

            $transactionData = [
                'transaction_info' => [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'type' => $transaction->type,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ],
                'user_info' => [
                    'user_id' => $transaction->user->id,
                    'name' => $transaction->user->full_name,
                    'email' => $transaction->user->email,
                    'phone' => $transaction->user->phone,
                    'role' => $transaction->user->role,
                ],
                'order_info' => $transaction->order ? [
                    'order_id' => $transaction->order->id,
                    'order_number' => $transaction->order->order_no,
                    'store_name' => $transaction->order->storeOrder->store->store_name ?? null,
                    'seller_name' => $transaction->order->storeOrder->store->user->full_name ?? null,
                ] : null,
                'payment_details' => [
                    'amount_formatted' => 'N' . number_format($transaction->amount, 2),
                    'status_color' => $this->getStatusColor($transaction->status),
                    'type_description' => $this->getTypeDescription($transaction->type),
                ]
            ];

            return ResponseHelper::success($transactionData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update transaction status
     */
    public function updateTransactionStatus(Request $request, $transactionId)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,successful,failed,cancelled',
                'notes' => 'nullable|string|max:500',
            ]);

            $transaction = Transaction::findOrFail($transactionId);
            $oldStatus = $transaction->status;
            
            $transaction->update([
                'status' => $request->status,
            ]);

            return ResponseHelper::success([
                'transaction_id' => $transaction->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'updated_at' => $transaction->updated_at,
            ], 'Transaction status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on transactions
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|in:update_status,mark_successful,mark_failed',
                'transaction_ids' => 'required|array|min:1',
                'transaction_ids.*' => 'integer|exists:transactions,id',
                'status' => 'required_if:action,update_status|in:pending,successful,failed,cancelled',
            ]);

            $transactionIds = $request->transaction_ids;
            $action = $request->action;

            switch ($action) {
                case 'update_status':
                    Transaction::whereIn('id', $transactionIds)->update(['status' => $request->status]);
                    return ResponseHelper::success(null, "Transactions status updated to {$request->status}");
                
                case 'mark_successful':
                    Transaction::whereIn('id', $transactionIds)->update(['status' => 'successful']);
                    return ResponseHelper::success(null, 'Transactions marked as successful');
                
                case 'mark_failed':
                    Transaction::whereIn('id', $transactionIds)->update(['status' => 'failed']);
                    return ResponseHelper::success(null, 'Transactions marked as failed');
            }
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get transaction statistics
     */
    public function getTransactionStatistics()
    {
        try {
            $stats = [
                'total_transactions' => Transaction::count(),
                'pending_transactions' => Transaction::where('status', 'pending')->count(),
                'successful_transactions' => Transaction::where('status', 'successful')->count(),
                'failed_transactions' => Transaction::where('status', 'failed')->count(),
                'cancelled_transactions' => Transaction::where('status', 'cancelled')->count(),
                'total_amount' => Transaction::where('status', 'successful')->sum('amount'),
                'average_transaction_amount' => Transaction::where('status', 'successful')->avg('amount'),
            ];

            // Transaction types breakdown
            $typeBreakdown = Transaction::selectRaw('type, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('type')
                ->get();

            // Monthly trends
            $monthlyStats = Transaction::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = "successful" THEN amount ELSE 0 END) as successful_amount,
                SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END) as successful_count
            ')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            return ResponseHelper::success([
                'current_stats' => $stats,
                'type_breakdown' => $typeBreakdown,
                'monthly_trends' => $monthlyStats,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get transaction analytics
     */
    public function getTransactionAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Daily transaction volume
            $dailyVolume = Transaction::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN status = "successful" THEN amount ELSE 0 END) as successful_amount
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Top users by transaction volume
            $topUsers = Transaction::selectRaw('
                user_id,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN status = "successful" THEN amount ELSE 0 END) as total_amount
            ')
            ->with('user:id,full_name,email')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

            return ResponseHelper::success([
                'daily_volume' => $dailyVolume,
                'top_users' => $topUsers,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format transactions data for response
     */
    private function formatTransactionsData($transactions)
    {
        return $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'tx_id' => $transaction->tx_id,
                'amount' => $transaction->amount,
                'amount_formatted' => 'N' . number_format($transaction->amount, 2),
                'status' => $transaction->status,
                'type' => $transaction->type,
                'user_name' => $transaction->user->full_name,
                'user_email' => $transaction->user->email,
                'created_at' => $transaction->created_at,
                'formatted_date' => $transaction->created_at->format('d-m-Y H:i A'),
                'status_color' => $this->getStatusColor($transaction->status),
            ];
        });
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor($status)
    {
        return match($status) {
            'successful' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'cancelled' => 'gray',
            default => 'blue'
        };
    }

    /**
     * Get type description
     */
    private function getTypeDescription($type)
    {
        return match($type) {
            'deposit' => 'Deposit',
            'withdrawal' => 'Withdrawal',
            'transfer' => 'Transfer',
            'bill_payment' => 'Bill Payment',
            'refund' => 'Refund',
            default => ucfirst($type)
        };
    }
}
