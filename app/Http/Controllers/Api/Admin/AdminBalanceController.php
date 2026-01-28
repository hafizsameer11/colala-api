<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Escrow;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBalanceController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get all user balances with filtering and pagination
     */
    public function getAllBalances(Request $request)
    {
        try {
            $query = User::with(['wallet', 'store']);

            // Apply filters
            if ($request->has('user_type') && $request->user_type !== 'all') {
                $query->where('role', $request->user_type);
            }

            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            // Apply period filter (priority over date_range for backward compatibility)
            if ($period) {
                $this->applyPeriodFilter($query, $period);
            } elseif ($request->has('date_range')) {
                // Legacy support for date_range parameter
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

            // Get summary statistics with period filtering
            $totalShoppingBalanceQuery = Wallet::query();
            $totalRewardBalanceQuery = Wallet::query();
            $totalReferralBalanceQuery = Wallet::query();
            $totalLoyaltyPointsQuery = Wallet::query();
            $totalEscrowBalanceQuery = Escrow::where('status', 'active');
            $buyerShoppingBalanceQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            });
            $sellerShoppingBalanceQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'seller');
            });
            $buyerRewardBalanceQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            });
            $sellerRewardBalanceQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'seller');
            });
            $buyerReferralBalanceQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            });
            $sellerReferralBalanceQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'seller');
            });
            $buyerLoyaltyPointsQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            });
            $sellerLoyaltyPointsQuery = Wallet::whereHas('user', function ($q) {
                $q->where('role', 'seller');
            });
            $buyerEscrowBalanceQuery = Escrow::whereHas('user', function ($q) {
                $q->where('role', 'buyer');
            })->where('status', 'active');
            $sellerEscrowBalanceQuery = Escrow::whereHas('user', function ($q) {
                $q->where('role', 'seller');
            })->where('status', 'active');
            
            if ($period) {
                $this->applyPeriodFilter($totalShoppingBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalRewardBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalReferralBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalLoyaltyPointsQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalEscrowBalanceQuery, $period);
                $this->applyPeriodFilter($buyerShoppingBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($sellerShoppingBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($buyerRewardBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($sellerRewardBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($buyerReferralBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($sellerReferralBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($buyerLoyaltyPointsQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($sellerLoyaltyPointsQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($buyerEscrowBalanceQuery, $period);
                $this->applyPeriodFilter($sellerEscrowBalanceQuery, $period);
            }
            
            $stats = [
                'total_shopping_balance' => $totalShoppingBalanceQuery->sum('shopping_balance'),
                'total_reward_balance' => $totalRewardBalanceQuery->sum('reward_balance'),
                'total_referral_balance' => $totalReferralBalanceQuery->sum('referral_balance'),
                'total_loyalty_points' => $totalLoyaltyPointsQuery->sum('loyality_points'),
                'total_escrow_balance' => $totalEscrowBalanceQuery->sum('amount'),
                'buyer_shopping_balance' => $buyerShoppingBalanceQuery->sum('shopping_balance'),
                'seller_shopping_balance' => $sellerShoppingBalanceQuery->sum('shopping_balance'),
                'buyer_reward_balance' => $buyerRewardBalanceQuery->sum('reward_balance'),
                'seller_reward_balance' => $sellerRewardBalanceQuery->sum('reward_balance'),
                'buyer_referral_balance' => $buyerReferralBalanceQuery->sum('referral_balance'),
                'seller_referral_balance' => $sellerReferralBalanceQuery->sum('referral_balance'),
                'buyer_loyalty_points' => $buyerLoyaltyPointsQuery->sum('loyality_points'),
                'seller_loyalty_points' => $sellerLoyaltyPointsQuery->sum('loyality_points'),
                'buyer_escrow_balance' => $buyerEscrowBalanceQuery->sum('amount'),
                'seller_escrow_balance' => $sellerEscrowBalanceQuery->sum('amount'),
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
                'store',
                'transactions' => function ($query) {
                    $query->latest()->limit(10);
                }
            ])->findOrFail($userId);

            // Get user's escrow balance
            $userEscrowBalance = Escrow::where('user_id', $userId)
                ->where('status', 'active')
                ->sum('amount');

            if (!$user->wallet) {
                return ResponseHelper::error('User does not have a wallet', 404);
            }

            // Determine profile picture based on user role
            $profilePicture = null;
            if ($user->role === 'seller' && $user->store && $user->store->profile_image) {
                $profilePicture = asset('storage/' . $user->store->profile_image);
            } elseif ($user->profile_picture) {
                $profilePicture = asset('storage/' . $user->profile_picture);
            }

            $balanceData = [
                'user_info' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'profile_picture' => $profilePicture,
                ],
                'wallet_info' => [
                    'id' => $user->wallet->id,
                    'shopping_balance' => $user->wallet->shopping_balance,
                    'reward_balance' => $user->wallet->reward_balance,
                    'referral_balance' => $user->wallet->referral_balance,
                    'loyalty_points' => $user->wallet->loyality_points,
                    'escrow_balance' => $userEscrowBalance,
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
                'shopping_balance' => 'nullable|numeric|min:0',
                'reward_balance' => 'nullable|numeric|min:0',
                'referral_balance' => 'nullable|numeric|min:0',
                'loyalty_points' => 'nullable|numeric|min:0',
                'action' => 'required|in:add,subtract,set',
            ]);

            $user = User::with('wallet')->findOrFail($userId);
            
            if (!$user->wallet) {
                return ResponseHelper::error('User does not have a wallet', 404);
            }

            $wallet = $user->wallet;
            $oldShoppingBalance = $wallet->shopping_balance;
            $oldRewardBalance = $wallet->reward_balance;
            $oldReferralBalance = $wallet->referral_balance;
            $oldLoyaltyPoints = $wallet->loyality_points;

            $newShoppingBalance = $oldShoppingBalance;
            $newRewardBalance = $oldRewardBalance;
            $newReferralBalance = $oldReferralBalance;
            $newLoyaltyPoints = $oldLoyaltyPoints;

            if ($request->has('shopping_balance')) {
                switch ($request->action) {
                    case 'add':
                        $newShoppingBalance = $oldShoppingBalance + $request->shopping_balance;
                        break;
                    case 'subtract':
                        $newShoppingBalance = max(0, $oldShoppingBalance - $request->shopping_balance);
                        break;
                    case 'set':
                        $newShoppingBalance = $request->shopping_balance;
                        break;
                }
            }

            if ($request->has('reward_balance')) {
                switch ($request->action) {
                    case 'add':
                        $newRewardBalance = $oldRewardBalance + $request->reward_balance;
                        break;
                    case 'subtract':
                        $newRewardBalance = max(0, $oldRewardBalance - $request->reward_balance);
                        break;
                    case 'set':
                        $newRewardBalance = $request->reward_balance;
                        break;
                }
            }

            if ($request->has('referral_balance')) {
                switch ($request->action) {
                    case 'add':
                        $newReferralBalance = $oldReferralBalance + $request->referral_balance;
                        break;
                    case 'subtract':
                        $newReferralBalance = max(0, $oldReferralBalance - $request->referral_balance);
                        break;
                    case 'set':
                        $newReferralBalance = $request->referral_balance;
                        break;
                }
            }

            if ($request->has('loyalty_points')) {
                switch ($request->action) {
                    case 'add':
                        $newLoyaltyPoints = $oldLoyaltyPoints + $request->loyalty_points;
                        break;
                    case 'subtract':
                        $newLoyaltyPoints = max(0, $oldLoyaltyPoints - $request->loyalty_points);
                        break;
                    case 'set':
                        $newLoyaltyPoints = $request->loyalty_points;
                        break;
                }
            }

            $wallet->update([
                'shopping_balance' => $newShoppingBalance,
                'reward_balance' => $newRewardBalance,
                'referral_balance' => $newReferralBalance,
                'loyality_points' => $newLoyaltyPoints,
            ]);

            return ResponseHelper::success([
                'user_id' => $user->id,
                'old_shopping_balance' => $oldShoppingBalance,
                'new_shopping_balance' => $newShoppingBalance,
                'old_reward_balance' => $oldRewardBalance,
                'new_reward_balance' => $newRewardBalance,
                'old_referral_balance' => $oldReferralBalance,
                'new_referral_balance' => $newReferralBalance,
                'old_loyalty_points' => $oldLoyaltyPoints,
                'new_loyalty_points' => $newLoyaltyPoints,
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
    public function getBalanceStatistics(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            $totalShoppingBalanceQuery = Wallet::query();
            $totalRewardBalanceQuery = Wallet::query();
            $totalReferralBalanceQuery = Wallet::query();
            $totalLoyaltyPointsQuery = Wallet::query();
            $totalEscrowBalanceQuery = Escrow::where('status', 'active');
            
            if ($period) {
                $this->applyPeriodFilter($totalShoppingBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalRewardBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalReferralBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalLoyaltyPointsQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalEscrowBalanceQuery, $period);
            }
            
            return ResponseHelper::success([
                'total_shopping_balance' => $totalShoppingBalanceQuery->sum('shopping_balance'),
                'total_reward_balance' => $totalRewardBalanceQuery->sum('reward_balance'),
                'total_referral_balance' => $totalReferralBalanceQuery->sum('referral_balance'),
                'total_loyalty_points' => $totalLoyaltyPointsQuery->sum('loyality_points'),
                'total_escrow_balance' => $totalEscrowBalanceQuery->sum('amount'),
            ], 'Balance statistics retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getBalanceAnalytics(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            $dateRange = $this->getDateRange($period);
            
            // Use period if provided, otherwise fall back to date_from/date_to
            if ($dateRange) {
                $dateFrom = $dateRange['start']->format('Y-m-d');
                $dateTo = $dateRange['end']->format('Y-m-d');
            } else {
                $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
                $dateTo = $request->get('date_to', now()->format('Y-m-d'));
            }

            // Balance trends
            $balanceTrends = Wallet::selectRaw('
                DATE(created_at) as date,
                SUM(shopping_balance) as total_shopping_balance,
                SUM(reward_balance) as total_reward_balance,
                SUM(referral_balance) as total_referral_balance,
                SUM(loyality_points) as total_loyalty_points
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Escrow trends
            $escrowTrends = Escrow::selectRaw('
                DATE(created_at) as date,
                SUM(amount) as total_escrow_balance
            ')
            ->where('status', 'active')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Top users by balance
            $topUsersByBalance = User::with('wallet')
                ->whereHas('wallet')
                ->join('wallets', 'users.id', '=', 'wallets.user_id')
                ->selectRaw('users.*, wallets.shopping_balance, wallets.reward_balance, wallets.referral_balance, wallets.loyality_points')
                ->orderByDesc('wallets.shopping_balance')
                ->limit(10)
                ->get();

            return ResponseHelper::success([
                'balance_trends' => $balanceTrends,
                'escrow_trends' => $escrowTrends,
                'top_users_by_balance' => $topUsersByBalance->map(function ($user) {
                    // Get user's escrow balance
                    $userEscrowBalance = Escrow::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->sum('amount');

                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'shopping_balance' => $user->shopping_balance,
                        'reward_balance' => $user->reward_balance,
                        'referral_balance' => $user->referral_balance,
                        'loyalty_points' => $user->loyality_points,
                        'escrow_balance' => $userEscrowBalance,
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
            // Get user's escrow balance
            $userEscrowBalance = Escrow::where('user_id', $user->id)
                ->where('status', 'active')
                ->sum('amount');

            // Determine profile picture based on user role
            $profilePicture = null;
            if ($user->role === 'seller' && $user->store && $user->store->profile_image) {
                $profilePicture = asset('storage/' . $user->store->profile_image);
            } elseif ($user->profile_picture) {
                $profilePicture = asset('storage/' . $user->profile_picture);
            }

            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'profile_picture' => $profilePicture,
                'shopping_balance' => $user->wallet ? $user->wallet->shopping_balance : 0,
                'reward_balance' => $user->wallet ? $user->wallet->reward_balance : 0,
                'referral_balance' => $user->wallet ? $user->wallet->referral_balance : 0,
                'loyalty_points' => $user->wallet ? $user->wallet->loyality_points : 0,
                'escrow_balance' => $userEscrowBalance,
                'created_at' => $user->created_at,
                'formatted_date' => $user->created_at->format('d-m-Y H:i A'),
            ];
        });
    }
}
