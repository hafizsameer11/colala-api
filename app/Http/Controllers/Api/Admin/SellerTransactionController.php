<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\Escrow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellerTransactionController extends Controller
{
    /**
     * Get all transactions for a specific seller
     */
    public function getSellerTransactions(Request $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Transaction::with(['user', 'order'])
                ->where('user_id', $userId);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by type
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tx_id', 'like', "%{$search}%")
                      ->orWhere('amount', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%");
                });
            }

            // Check if export is requested
            if ($request->has('export') && $request->export == 'true') {
                $transactions = $query->latest()->get();
                return ResponseHelper::success($transactions, 'Seller transactions exported successfully');
            }

            $transactions = $query->latest()->paginate(20);

            // Get summary statistics
            $allTransactions = Transaction::where('user_id', $userId)->count();
            $pendingTransactions = Transaction::where('user_id', $userId)->where('status', 'pending')->count();
            $successfulTransactions = Transaction::where('user_id', $userId)->where('status', 'successful')->count();
            $failedTransactions = Transaction::where('user_id', $userId)->where('status', 'failed')->count();
            $expiredTransactions = Transaction::where('user_id', $userId)->where('status', 'expired')->count();

            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => 'N' . number_format($transaction->amount, 0),
                    'formatted_amount' => $this->formatTransactionAmount($transaction),
                    'type' => ucfirst(str_replace('_', ' ', $transaction->type)),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'status_icon' => $this->getTransactionStatusIcon($transaction->status),
                    'payment_method' => 'Bank Transfer', // Transaction model doesn't have payment_method field
                    'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                    'channel' => $transaction->channel ?? 'Bank Transfer',
                    'tx_date' => $transaction->created_at->format('d-m-Y/h:iA'),
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s'),
                    'order' => $transaction->order ? [
                        'id' => $transaction->order->id,
                        'order_no' => $transaction->order->order_no,
                        'status' => $transaction->order->status
                    ] : null,
                    'account_details' => $this->getAccountDetails($transaction)
                ];
            });

            return ResponseHelper::success([
                'transactions' => $transactions,
                'summary_stats' => [
                    'all_transactions' => [
                        'count' => $allTransactions,
                        'increase' => 6, // Mock data
                        'color' => 'red'
                    ],
                    'pending_transactions' => [
                        'count' => $pendingTransactions,
                        'increase' => 6, // Mock data
                        'color' => 'red'
                    ],
                    'expired_transactions' => [
                        'count' => $expiredTransactions,
                        'increase' => 6, // Mock data
                        'color' => 'red'
                    ]
                ]
            ], 'Seller transactions retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed transaction information
     */
    public function getTransactionDetails($userId, $transactionId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $transaction = Transaction::with(['user', 'order.storeOrders.store'])
                ->where('user_id', $userId)
                ->findOrFail($transactionId);

            $transactionDetails = [
                'transaction_info' => [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => $transaction->amount,
                    'formatted_amount' => $this->formatTransactionAmount($transaction),
                    'type' => ucfirst(str_replace('_', ' ', $transaction->type)),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'status_icon' => $this->getTransactionStatusIcon($transaction->status),
                    'payment_method' => 'Bank Transfer', // Transaction model doesn't have payment_method field
                    'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                    'channel' => $transaction->channel ?? 'Bank Transfer',
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $transaction->updated_at->format('d-m-Y H:i:s')
                ],
                'user_info' => [
                    'id' => $transaction->user->id,
                    'name' => $transaction->user->full_name,
                    'email' => $transaction->user->email,
                    'phone' => $transaction->user->phone,
                    'profile_picture' => $transaction->user->profile_picture ? asset('storage/' . $transaction->user->profile_picture) : null
                ],
                'order_info' => $transaction->order ? [
                    'id' => $transaction->order->id,
                    'order_no' => $transaction->order->order_no,
                    'status' => $transaction->order->status,
                    'payment_status' => $transaction->order->payment_status,
                    'grand_total' => 'N' . number_format($transaction->order->grand_total, 0),
                    'store_orders' => $transaction->order->storeOrders->map(function ($storeOrder) {
                        return [
                            'id' => $storeOrder->id,
                            'store_name' => $storeOrder->store->store_name,
                            'subtotal' => 'N' . number_format($storeOrder->subtotal, 0),
                            'delivery_fee' => 'N' . number_format($storeOrder->delivery_fee, 0),
                            'total' => 'N' . number_format($storeOrder->total, 0),
                            'status' => $storeOrder->status
                        ];
                    })
                ] : null,
                'account_details' => $this->getAccountDetails($transaction),
                'escrow_info' => $this->getEscrowInfo($transaction),
                'timeline' => $this->getTransactionTimeline($transaction)
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
    public function updateTransactionStatus(Request $request, $userId, $transactionId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,successful,failed,expired,cancelled'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $transaction = Transaction::where('user_id', $userId)->findOrFail($transactionId);
            $transaction->update(['status' => $request->status]);

            return ResponseHelper::success([
                'transaction_id' => $transaction->id,
                'status' => ucfirst($transaction->status),
                'status_color' => $this->getTransactionStatusColor($transaction->status),
                'updated_at' => $transaction->updated_at->format('d-m-Y H:i:s')
            ], 'Transaction status updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get transaction statistics for seller
     */
    public function getTransactionStatistics($userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $totalTransactions = Transaction::where('user_id', $userId)->count();
            $pendingTransactions = Transaction::where('user_id', $userId)->where('status', 'pending')->count();
            $successfulTransactions = Transaction::where('user_id', $userId)->where('status', 'successful')->count();
            $failedTransactions = Transaction::where('user_id', $userId)->where('status', 'failed')->count();
            $expiredTransactions = Transaction::where('user_id', $userId)->where('status', 'expired')->count();

            $totalAmount = Transaction::where('user_id', $userId)->sum('amount');
            $successfulAmount = Transaction::where('user_id', $userId)->where('status', 'successful')->sum('amount');
            $pendingAmount = Transaction::where('user_id', $userId)->where('status', 'pending')->sum('amount');

            $depositTransactions = Transaction::where('user_id', $userId)->where('type', 'deposit')->count();
            $withdrawalTransactions = Transaction::where('user_id', $userId)->where('type', 'withdrawl')->count();
            $orderPaymentTransactions = Transaction::where('user_id', $userId)->where('type', 'order_payment')->count();

            return ResponseHelper::success([
                'transaction_counts' => [
                    'total' => $totalTransactions,
                    'pending' => $pendingTransactions,
                    'successful' => $successfulTransactions,
                    'failed' => $failedTransactions,
                    'expired' => $expiredTransactions
                ],
                'amounts' => [
                    'total' => [
                        'amount' => $totalAmount,
                        'formatted' => 'N' . number_format($totalAmount, 0)
                    ],
                    'successful' => [
                        'amount' => $successfulAmount,
                        'formatted' => 'N' . number_format($successfulAmount, 0)
                    ],
                    'pending' => [
                        'amount' => $pendingAmount,
                        'formatted' => 'N' . number_format($pendingAmount, 0)
                    ]
                ],
                'transaction_types' => [
                    'deposit' => $depositTransactions,
                    'withdrawal' => $withdrawalTransactions,
                    'order_payment' => $orderPaymentTransactions
                ],
                'success_rate' => $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 2) : 0
            ], 'Transaction statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Download transaction receipt
     */
    public function downloadTransactionReceipt($userId, $transactionId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $transaction = Transaction::with(['user', 'order'])
                ->where('user_id', $userId)
                ->findOrFail($transactionId);

            // Generate receipt data
            $receiptData = [
                'transaction_id' => $transaction->tx_id,
                'amount' => 'N' . number_format($transaction->amount, 0),
                'type' => ucfirst(str_replace('_', ' ', $transaction->type)),
                'status' => ucfirst($transaction->status),
                'date' => $transaction->created_at->format('d-m-Y H:i:s'),
                'user_name' => $transaction->user->full_name,
                'user_email' => $transaction->user->email,
                'store_name' => $store->store_name
            ];

            return ResponseHelper::success([
                'receipt_data' => $receiptData,
                'download_url' => route('admin.transaction.receipt', ['userId' => $userId, 'transactionId' => $transactionId])
            ], 'Transaction receipt generated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format transaction amount with sign
     */
    private function formatTransactionAmount($transaction)
    {
        $amount = $transaction->amount;
        $sign = in_array($transaction->type, ['deposit', 'order_payment']) ? '+' : '-';
        return $sign . 'N' . number_format($amount, 0);
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
            'expired' => 'gray',
            'cancelled' => 'red'
        ];

        return $colors[$status] ?? 'gray';
    }

    /**
     * Get transaction status icon
     */
    private function getTransactionStatusIcon($status)
    {
        $icons = [
            'successful' => 'checkmark',
            'pending' => 'clock',
            'failed' => 'x',
            'expired' => 'exclamation',
            'cancelled' => 'x'
        ];

        return $icons[$status] ?? 'question';
    }

    /**
     * Get account details for transaction
     */
    private function getAccountDetails($transaction)
    {
        // Mock account details - in real implementation, this would come from payment gateway
        return [
            'account_number' => '1234567890',
            'account_name' => $transaction->user->full_name,
            'bank_name' => 'Maybank',
            'routing_number' => '123456'
        ];
    }

    /**
     * Get escrow information for transaction
     */
    private function getEscrowInfo($transaction)
    {
        if (!$transaction->order) {
            return null;
        }

        $escrowAmount = Escrow::where('order_id', $transaction->order->id)->sum('amount');
        
        return [
            'escrow_amount' => $escrowAmount,
            'formatted_escrow_amount' => 'N' . number_format($escrowAmount, 0),
            'escrow_status' => 'locked',
            'release_date' => $transaction->order->created_at->addDays(7)->format('d-m-Y')
        ];
    }

    /**
     * Get transaction timeline
     */
    private function getTransactionTimeline($transaction)
    {
        $timeline = [
            [
                'status' => 'Transaction Initiated',
                'date' => $transaction->created_at->format('d-m-Y H:i:s'),
                'description' => 'Transaction was initiated',
                'completed' => true
            ]
        ];

        if ($transaction->status !== 'pending') {
            $timeline[] = [
                'status' => 'Processing',
                'date' => $transaction->updated_at->format('d-m-Y H:i:s'),
                'description' => 'Transaction is being processed',
                'completed' => in_array($transaction->status, ['successful', 'failed'])
            ];
        }

        if (in_array($transaction->status, ['successful', 'failed', 'expired', 'cancelled'])) {
            $timeline[] = [
                'status' => ucfirst($transaction->status),
                'date' => $transaction->updated_at->format('d-m-Y H:i:s'),
                'description' => 'Transaction has been ' . $transaction->status,
                'completed' => true
            ];
        }

        return $timeline;
    }
}
