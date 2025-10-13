<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBalanceController extends Controller
{
    /**
     * Get all user balances with filtering and pagination
     */
    public function getAllBalances(Request $request)
    {
        try {
            $query = User::with(['wallet']);

            // Apply filters
            if ($request->has('user_type') && $request->user_type !== 'all') {
                $query->where('role', $request->user_type);
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
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $users = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_shopping_balance' => Wallet::sum('balance'),
                'total_escrow_balance' => Wallet::sum('escrow_balance'),
                'total_points_balance' => Wallet::sum('points_balance'),
                'buyer_shopping_balance' => Wallet::whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->sum('balance'),
                'seller_shopping_balance' => Wallet::whereHas('user', function ($q) {
                    $q->where('role', 'seller');
                })->sum('balance'),
                'buyer_escrow_balance' => Wallet::whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->sum('escrow_balance'),
                'seller_escrow_balance' => Wallet::whereHas('user', function ($q) {
                    $q->where('role', 'seller');
                })->sum('escrow_balance'),
                'buyer_points_balance' => Wallet::whereHas('user', function ($q) {
                    $q->where('role', 'buyer');
                })->sum('points_balance'),
                'seller_points_balance' => Wallet::whereHas('user', function ($q) {
                    $q->where('role', 'seller');
                })->sum('points_balance'),
            ];

            return ResponseHelper::success([
                'users' => $this->formatBalancesData($users),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed user balance information
     */
    public function getUserBalanceDetails($userId)
    {
        try {
            $user = User::with([
                'wallet',
                'transactions' => function ($query) {
                    $query->latest()->limit(10);
                }
            ])->findOrFail($userId);

            if (!$user->wallet) {
                return ResponseHelper::error('User does not have a wallet', 404);
            }

            $balanceData = [
                'user_info' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                ],
                'wallet_info' => [
                    'id' => $user->wallet->id,
                    'balance' => $user->wallet->balance,
                    'escrow_balance' => $user->wallet->escrow_balance,
                    'points_balance' => $user->wallet->points_balance,
                    'created_at' => $user->wallet->created_at,
                    'updated_at' => $user->wallet->updated_at,
                ],
                'recent_transactions' => $user->transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'tx_id' => $transaction->tx_id,
                        'amount' => $transaction->amount,
                        'status' => $transaction->status,
                        'type' => $transaction->type,
                        'created_at' => $transaction->created_at,
                        'formatted_date' => $transaction->created_at->format('d-m-Y H:i A'),
                    ];
                }),
                'balance_statistics' => [
                    'total_transactions' => $user->transactions->count(),
                    'total_deposits' => $user->transactions->where('type', 'deposit')->sum('amount'),
                    'total_withdrawals' => $user->transactions->where('type', 'withdrawal')->sum('amount'),
                    'successful_transactions' => $user->transactions->where('status', 'successful')->count(),
                    'failed_transactions' => $user->transactions->where('status', 'failed')->count(),
                ],
            ];

            return ResponseHelper::success($balanceData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update user wallet balance
     */
    public function updateUserBalance(Request $request, $userId)
    {
        try {
            $request->validate([
                'balance' => 'nullable|numeric|min:0',
                'escrow_balance' => 'nullable|numeric|min:0',
                'points_balance' => 'nullable|numeric|min:0',
                'action' => 'required|in:add,subtract,set',
            ]);

            $user = User::with('wallet')->findOrFail($userId);
            
            if (!$user->wallet) {
                return ResponseHelper::error('User does not have a wallet', 404);
            }

            $wallet = $user->wallet;
            $oldBalance = $wallet->balance;
            $oldEscrowBalance = $wallet->escrow_balance;
            $oldPointsBalance = $wallet->points_balance;

            $newBalance = $oldBalance;
            $newEscrowBalance = $oldEscrowBalance;
            $newPointsBalance = $oldPointsBalance;

            if ($request->has('balance')) {
                switch ($request->action) {
                    case 'add':
                        $newBalance = $oldBalance + $request->balance;
                        break;
                    case 'subtract':
                        $newBalance = max(0, $oldBalance - $request->balance);
                        break;
                    case 'set':
                        $newBalance = $request->balance;
                        break;
                }
            }

            if ($request->has('escrow_balance')) {
                switch ($request->action) {
                    case 'add':
                        $newEscrowBalance = $oldEscrowBalance + $request->escrow_balance;
                        break;
                    case 'subtract':
                        $newEscrowBalance = max(0, $oldEscrowBalance - $request->escrow_balance);
                        break;
                    case 'set':
                        $newEscrowBalance = $request->escrow_balance;
                        break;
                }
            }

            if ($request->has('points_balance')) {
                switch ($request->action) {
                    case 'add':
                        $newPointsBalance = $oldPointsBalance + $request->points_balance;
                        break;
                    case 'subtract':
                        $newPointsBalance = max(0, $oldPointsBalance - $request->points_balance);
                        break;
                    case 'set':
                        $newPointsBalance = $request->points_balance;
                        break;
                }
            }

            $wallet->update([
                'balance' => $newBalance,
                'escrow_balance' => $newEscrowBalance,
                'points_balance' => $newPointsBalance,
            ]);

            return ResponseHelper::success([
                'user_id' => $user->id,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'old_escrow_balance' => $oldEscrowBalance,
                'new_escrow_balance' => $newEscrowBalance,
                'old_points_balance' => $oldPointsBalance,
                'new_points_balance' => $newPointsBalance,
                'action' => $request->action,
                'updated_at' => $wallet->updated_at,
            ], 'User balance updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get balance analytics
     */
    public function getBalanceAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Balance trends
            $balanceTrends = Wallet::selectRaw('
                DATE(created_at) as date,
                SUM(balance) as total_balance,
                SUM(escrow_balance) as total_escrow_balance,
                SUM(points_balance) as total_points_balance
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Top users by balance
            $topUsersByBalance = User::with('wallet')
                ->whereHas('wallet')
                ->join('wallets', 'users.id', '=', 'wallets.user_id')
                ->selectRaw('users.*, wallets.balance, wallets.escrow_balance, wallets.points_balance')
                ->orderByDesc('wallets.balance')
                ->limit(10)
                ->get();

            return ResponseHelper::success([
                'balance_trends' => $balanceTrends,
                'top_users_by_balance' => $topUsersByBalance->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'balance' => $user->balance,
                        'escrow_balance' => $user->escrow_balance,
                        'points_balance' => $user->points_balance,
                    ];
                }),
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
     * Format balances data for response
     */
    private function formatBalancesData($users)
    {
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'shopping_balance' => $user->wallet ? $user->wallet->balance : 0,
                'escrow_balance' => $user->wallet ? $user->wallet->escrow_balance : 0,
                'points_balance' => $user->wallet ? $user->wallet->points_balance : 0,
                'created_at' => $user->created_at,
                'formatted_date' => $user->created_at->format('d-m-Y H:i A'),
            ];
        });
    }
}
