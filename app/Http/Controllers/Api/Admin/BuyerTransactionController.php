<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BuyerTransactionController extends Controller
{
    /**
     * Get all buyer transactions with summary stats
     */
    public function index(Request $request)
    {
        try {
            $query = Transaction::with(['user', 'order'])
                ->whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                });

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Type filter
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // Date filter
            if ($request->has('date') && $request->date !== 'all') {
                if ($request->date === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($request->date === 'week') {
                    $query->whereBetween('created_at', [now()->subWeek(), now()]);
                } elseif ($request->date === 'month') {
                    $query->whereMonth('created_at', now()->month);
                }
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tx_id', 'like', "%{$search}%")
                      ->orWhere('amount', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $transactions = $query->latest()->paginate(15);

            // Get summary stats (only for buyers)
            $totalTransactions = Transaction::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->count();
            $pendingTransactions = Transaction::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->where('status', 'pending')->count();
            $successfulTransactions = Transaction::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->where('status', 'successful')->count();
            $failedTransactions = Transaction::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->where('status', 'failed')->count();

            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'buyer' => [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->full_name,
                        'email' => $transaction->user->email,
                        'phone' => $transaction->user->phone
                    ],
                    'amount' => 'N' . number_format($transaction->amount, 0),
                    'type' => ucfirst($transaction->type),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'payment_method' => 'Unknown', // Transaction model doesn't have payment_method field
                    'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                    'tx_date' => $transaction->created_at->format('d-m-Y/h:iA'),
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s'),
                    'order' => $transaction->order ? [
                        'id' => $transaction->order->id,
                        'order_no' => $transaction->order->order_no,
                        'status' => $transaction->order->payment_status ?? 'Unknown'
                    ] : null
                ];
            });

            $summaryStats = [
                'total_transactions' => [
                    'count' => $totalTransactions,
                    'increase' => 5, // Mock data
                    'color' => 'red'
                ],
                'pending_transactions' => [
                    'count' => $pendingTransactions,
                    'increase' => 5, // Mock data
                    'color' => 'red'
                ],
                'successful_transactions' => [
                    'count' => $successfulTransactions,
                    'increase' => 5, // Mock data
                    'color' => 'red'
                ],
                'failed_transactions' => [
                    'count' => $failedTransactions,
                    'increase' => 5, // Mock data
                    'color' => 'red'
                ]
            ];

            return ResponseHelper::success([
                'transactions' => $transactions,
                'summary_stats' => $summaryStats
            ], 'Buyer transactions retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Filter buyer transactions
     */
    public function filter(Request $request)
    {
        try {
            $query = Transaction::with(['user', 'order'])
                ->whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                });

            $status = $request->get('status', 'all');
            $type = $request->get('type', 'all');
            $date = $request->get('date', 'all');
            $search = $request->get('search', '');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($type !== 'all') {
                $query->where('type', $type);
            }

            if ($date !== 'all') {
                if ($date === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($date === 'week') {
                    $query->whereBetween('created_at', [now()->subWeek(), now()]);
                } elseif ($date === 'month') {
                    $query->whereMonth('created_at', now()->month);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tx_id', 'like', "%{$search}%")
                      ->orWhere('amount', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $transactions = $query->latest()->get()->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'buyer' => [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->full_name,
                        'email' => $transaction->user->email,
                        'phone' => $transaction->user->phone
                    ],
                    'amount' => 'N' . number_format($transaction->amount, 0),
                    'type' => ucfirst($transaction->type),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'payment_method' => 'Unknown', // Transaction model doesn't have payment_method field
                    'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                    'tx_date' => $transaction->created_at->format('d-m-Y/h:iA'),
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s'),
                    'order' => $transaction->order ? [
                        'id' => $transaction->order->id,
                        'order_no' => $transaction->order->order_no,
                        'status' => $transaction->order->payment_status ?? 'Unknown'
                    ] : null
                ];
            });

            return ResponseHelper::success($transactions, 'Filtered transactions retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on buyer transactions
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'transaction_ids' => 'required|array',
                'action' => 'required|string|in:approve,reject,delete'
            ]);

            $transactionIds = $request->transaction_ids;
            $action = $request->action;

            if ($action === 'approve') {
                Transaction::whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->whereIn('id', $transactionIds)->update(['status' => 'successful']);
                $message = "Transactions approved successfully";
            } elseif ($action === 'reject') {
                Transaction::whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->whereIn('id', $transactionIds)->update(['status' => 'failed']);
                $message = "Transactions rejected successfully";
            } else {
                Transaction::whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->whereIn('id', $transactionIds)->delete();
                $message = "Transactions deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed transaction information
     */
    public function transactionDetails($transactionId)
    {
        try {
            $transaction = Transaction::with(['user', 'order.storeOrders.store'])
                ->whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->findOrFail($transactionId);

            $transactionDetails = [
                'transaction_info' => [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => [
                        'formatted' => 'N' . number_format($transaction->amount, 0),
                        'raw' => $transaction->amount,
                        'sign' => $transaction->type === 'deposit' ? '+' : '-'
                    ],
                    'type' => ucfirst($transaction->type),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'payment_method' => 'Unknown', // Transaction model doesn't have payment_method field
                    'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                    'description' => 'Transaction', // Transaction model doesn't have description field
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $transaction->updated_at->format('d-m-Y H:i:s')
                ],
                'buyer_info' => [
                    'id' => $transaction->user->id,
                    'name' => $transaction->user->full_name,
                    'email' => $transaction->user->email,
                    'phone' => $transaction->user->phone,
                    'profile_picture' => $transaction->user->profile_picture ? asset('storage/' . $transaction->user->profile_picture) : null,
                    'user_code' => $transaction->user->user_code,
                    'wallet_balance' => $transaction->user->wallet ? [
                        'shopping_balance' => 'N' . number_format($transaction->user->wallet->shopping_balance, 2),
                        'reward_balance' => 'N' . number_format($transaction->user->wallet->reward_balance, 2),
                        'referral_balance' => 'N' . number_format($transaction->user->wallet->referral_balance, 2),
                        'loyalty_points' => $transaction->user->wallet->loyality_points
                    ] : null
                ],
                'order_info' => $transaction->order ? [
                    'id' => $transaction->order->id,
                    'order_no' => $transaction->order->order_no,
                    'status' => $transaction->order->payment_status ?? 'Unknown',
                    'total_amount' => 'N' . number_format($transaction->order->grand_total, 2),
                    'store' => $transaction->order->storeOrders->first() ? [
                        'id' => $transaction->order->storeOrders->first()->store->id,
                        'name' => $transaction->order->storeOrders->first()->store->store_name,
                        'seller' => $transaction->order->storeOrders->first()->store->user->full_name ?? 'Unknown'
                    ] : null,
                    'order_date' => $transaction->order->created_at->format('d-m-Y h:iA')
                ] : null,
                'payment_details' => [
                    'method' => 'Unknown', // Transaction model doesn't have payment_method field
                    'channel' => 'Unknown', // Transaction model doesn't have these fields
                    'gateway' => 'Unknown',
                    'gateway_reference' => null,
                    'gateway_response' => null,
                    'fees' => 0,
                    'net_amount' => $transaction->net_amount ?? $transaction->amount
                ],
                'timeline' => [
                    'initiated' => $transaction->created_at->format('d-m-Y h:iA'),
                    'processed' => $transaction->status === 'successful' ? $transaction->updated_at->format('d-m-Y h:iA') : null,
                    'duration' => $transaction->status === 'successful' ? 
                        $transaction->created_at->diffForHumans($transaction->updated_at) : null
                ]
            ];

            return ResponseHelper::success($transactionDetails, 'Transaction details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
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
                'status' => 'required|string|in:pending,successful,failed,cancelled',
                'notes' => 'nullable|string|max:500'
            ]);

            $transaction = Transaction::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->findOrFail($transactionId);

            $transaction->update([
                'status' => $request->status,
                'description' => $request->notes ? 'Transaction | Admin Note: ' . $request->notes : 'Transaction' // Transaction model doesn't have description field
            ]);

            return ResponseHelper::success(null, 'Transaction status updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get transaction analytics
     */
    public function analytics(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'month'); // today, week, month, year
            
            $query = Transaction::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            });

            // Apply date range
            if ($dateRange === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($dateRange === 'week') {
                $query->whereBetween('created_at', [now()->subWeek(), now()]);
            } elseif ($dateRange === 'month') {
                $query->whereMonth('created_at', now()->month);
            } elseif ($dateRange === 'year') {
                $query->whereYear('created_at', now()->year);
            }

            $analytics = [
                'summary' => [
                    'total_transactions' => $query->count(),
                    'total_amount' => 'N' . number_format($query->sum('amount'), 0),
                    'successful_transactions' => $query->where('status', 'successful')->count(),
                    'successful_amount' => 'N' . number_format($query->where('status', 'successful')->sum('amount'), 0),
                    'failed_transactions' => $query->where('status', 'failed')->count(),
                    'pending_transactions' => $query->where('status', 'pending')->count()
                ],
                'by_type' => [
                    'deposits' => $query->where('type', 'deposit')->count(),
                    'withdrawals' => $query->where('type', 'withdrawal')->count(),
                    'payments' => $query->where('type', 'payment')->count(),
                    'refunds' => $query->where('type', 'refund')->count()
                ],
                'by_status' => [
                    'successful' => $query->where('status', 'successful')->count(),
                    'failed' => $query->where('status', 'failed')->count(),
                    'pending' => $query->where('status', 'pending')->count(),
                    'cancelled' => $query->where('status', 'cancelled')->count()
                ],
                'by_payment_method' => [
                    'card' => 0, // Transaction model doesn't have payment_method field
                    'bank_transfer' => 0,
                    'wallet' => 0,
                    'other' => 0
                ]
            ];

            return ResponseHelper::success($analytics, 'Transaction analytics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get transaction status color
     */
    private function getTransactionStatusColor($status)
    {
        $colors = [
            'successful' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'cancelled' => 'gray'
        ];

        return $colors[$status] ?? 'gray';
    }
}
