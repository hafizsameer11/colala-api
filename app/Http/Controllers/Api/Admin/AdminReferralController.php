<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralFaq;
use App\Models\Wallet;
use App\Models\LoyaltySetting;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminReferralController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get referral dashboard statistics and list of referrers
     */
    public function getReferralDashboard(Request $request)
    {
        try {
            // Get total referred users
            $totalReferred = User::whereNotNull('referral_code')->count();
            
            // Get buyers referred today
            $todayReferred = User::whereNotNull('referral_code')
                ->where('role', 'buyer')
                ->whereDate('created_at', today())
                ->count();
            
            // Get sellers referred
            $sellersReferred = User::whereNotNull('referral_code')
                ->where('role', 'seller')
                ->count();

            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            // Get referrers with their referral counts and wallet information
            $referrersQuery = User::whereHas('referrals')
                ->withCount('referrals')
                ->with(['wallet'])
                ->select('id', 'full_name', 'email', 'user_code', 'referral_code', 'role', 'created_at');
            
            // Apply date filter (period > date_from/date_to > date_range)
            $this->applyDateFilter($referrersQuery, $request);
            
            // Check if export is requested
            if ($request->has('export') && $request->export == 'true') {
                $referrers = $referrersQuery->get();
                return ResponseHelper::success($referrers, 'Referrers exported successfully');
            }

            $referrers = $referrersQuery->paginate($request->get('per_page', 20));

            // Calculate amount earned for each referrer from wallet
            $referrers->getCollection()->transform(function ($referrer) {
                $wallet = $referrer->wallet;
                $referrer->referral_balance = $wallet ? $wallet->referral_balance : 0;
                $referrer->shopping_balance = $wallet ? $wallet->shopping_balance : 0;
                $referrer->reward_balance = $wallet ? $wallet->reward_balance : 0;
                $referrer->loyalty_points = $wallet ? $wallet->loyality_points : 0;
                return $referrer;
            });

            // Get referral statistics from wallet with period filtering
            $totalReferredQuery = User::whereNotNull('referral_code');
            $todayReferredQuery = User::whereNotNull('referral_code')->where('role', 'buyer');
            $sellersReferredQuery = User::whereNotNull('referral_code')->where('role', 'seller');
            $totalReferrersQuery = User::whereHas('referrals');
            $totalReferralBalanceQuery = Wallet::query();
            $totalShoppingBalanceQuery = Wallet::query();
            $totalRewardBalanceQuery = Wallet::query();
            $totalLoyaltyPointsQuery = Wallet::query();
            
            if ($period) {
                $this->applyPeriodFilter($totalReferredQuery, $period);
                if ($period === 'today') {
                    $todayReferredQuery->whereDate('created_at', today());
                } else {
                    $this->applyPeriodFilter($todayReferredQuery, $period);
                }
                $this->applyPeriodFilter($sellersReferredQuery, $period);
                $this->applyPeriodFilter($totalReferrersQuery, $period);
                $this->applyPeriodFilter($totalReferralBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalShoppingBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalRewardBalanceQuery, $period, 'wallets.created_at');
                $this->applyPeriodFilter($totalLoyaltyPointsQuery, $period, 'wallets.created_at');
            }
            
            $stats = [
                'total_referred' => $totalReferredQuery->count(),
                'today_referred' => $todayReferredQuery->count(),
                'sellers_referred' => $sellersReferredQuery->count(),
                'total_referrers' => $totalReferrersQuery->count(),
                'total_referral_balance' => $totalReferralBalanceQuery->sum('referral_balance'),
                'total_shopping_balance' => $totalShoppingBalanceQuery->sum('shopping_balance'),
                'total_reward_balance' => $totalRewardBalanceQuery->sum('reward_balance'),
                'total_loyalty_points' => $totalLoyaltyPointsQuery->sum('loyality_points'),
            ];

            return ResponseHelper::success([
                'statistics' => $stats,
                'referrers' => $referrers,
                'pagination' => [
                    'current_page' => $referrers->currentPage(),
                    'last_page' => $referrers->lastPage(),
                    'per_page' => $referrers->perPage(),
                    'total' => $referrers->total(),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get users referred by a specific referrer
     */
    public function getReferredUsers(Request $request, $userId)
    {
        try {
            $referrer = User::findOrFail($userId);
            
            // Check if export is requested
            if ($request->has('export') && $request->export == 'true') {
                $referredUsers = User::where('referral_code', $referrer->user_code)
                    ->select('id', 'full_name', 'email', 'user_code', 'role', 'created_at')
                    ->get();
                return ResponseHelper::success($referredUsers, 'Referred users exported successfully');
            }

            $referredUsers = User::where('referral_code', $referrer->user_code)
                ->select('id', 'full_name', 'email', 'user_code', 'role', 'created_at')
                ->paginate($request->get('per_page', 20));

            $referrerStats = [
                'referrer_name' => $referrer->full_name,
                'referrer_user_code' => $referrer->user_code,
                'total_referred' => $referredUsers->total(),
                'buyers_referred' => User::where('referral_code', $referrer->user_code)->where('role', 'buyer')->count(),
                'sellers_referred' => User::where('referral_code', $referrer->user_code)->where('role', 'seller')->count(),
            ];

            return ResponseHelper::success([
                'referrer_stats' => $referrerStats,
                'referred_users' => $referredUsers,
                'pagination' => [
                    'current_page' => $referredUsers->currentPage(),
                    'last_page' => $referredUsers->lastPage(),
                    'per_page' => $referredUsers->perPage(),
                    'total' => $referredUsers->total(),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get referral details for a specific user
     */
    public function getReferralDetails($userId)
    {
        try {
            $user = User::with(['referralEarning', 'referrals'])
                ->findOrFail($userId);

            $referralDetails = [
                'user_info' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'user_code' => $user->user_code,
                    'referral_code' => $user->referral_code,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                ],
                'referral_earnings' => $user->referralEarning ? [
                    'total_earned' => $user->referralEarning->total_earned,
                    'total_withdrawn' => $user->referralEarning->total_withdrawn,
                    'current_balance' => $user->referralEarning->current_balance,
                ] : null,
                'referral_stats' => [
                    'total_referred' => $user->referrals->count(),
                    'buyer_referrals' => $user->referrals->where('role', 'buyer')->count(),
                    'seller_referrals' => $user->referrals->where('role', 'seller')->count(),
                ],
                'recent_referrals' => $user->referrals->take(10)->map(function ($referredUser) {
                    return [
                        'id' => $referredUser->id,
                        'referred_user_name' => $referredUser->full_name,
                        'referred_user_email' => $referredUser->email,
                        'role' => $referredUser->role,
                        'created_at' => $referredUser->created_at,
                    ];
                }),
            ];

            return ResponseHelper::success($referralDetails);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get referral settings
     */
    public function getReferralSettings()
    {
        try {
            $settings = LoyaltySetting::first();
            
            $referralSettings = [
                'points_per_referral' => $settings ? $settings->points_per_referral : 0,
                'enable_referral_points' => $settings ? $settings->enable_referral_points : false,
                'referral_bonus_amount' => $settings ? $settings->referral_bonus_amount ?? 0 : 0,
                'referral_percentage' => $settings ? $settings->referral_percentage ?? 0 : 0,
            ];

            return ResponseHelper::success($referralSettings);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update referral settings
     */
    public function updateReferralSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'points_per_referral' => 'required|integer|min:0',
                'enable_referral_points' => 'required|boolean',
                'referral_bonus_amount' => 'required|numeric|min:0',
                'referral_percentage' => 'required|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error('Validation failed: ' . $validator->errors()->first(), 422);
            }

            $settings = LoyaltySetting::firstOrCreate([]);
            $settings->update([
                'points_per_referral' => $request->points_per_referral,
                'enable_referral_points' => $request->enable_referral_points,
                'referral_bonus_amount' => $request->referral_bonus_amount,
                'referral_percentage' => $request->referral_percentage,
            ]);

            return ResponseHelper::success($settings, 'Referral settings updated successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get referral analytics
     */
    public function getReferralAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Referral trends over time
            $referralTrends = User::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_referrals,
                SUM(CASE WHEN role = "buyer" THEN 1 ELSE 0 END) as buyer_referrals,
                SUM(CASE WHEN role = "seller" THEN 1 ELSE 0 END) as seller_referrals
            ')
            ->whereNotNull('referral_code')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Top referrers
            $topReferrers = User::whereHas('referrals')
                ->withCount('referrals')
                ->with(['wallet'])
                ->select('id', 'full_name', 'user_code')
                ->orderBy('referrals_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($referrer) {
                    return [
                        'id' => $referrer->id,
                        'name' => $referrer->full_name,
                        'user_code' => $referrer->user_code,
                        'total_referrals' => $referrer->referrals_count,
                        'referral_balance' => $referrer->wallet ? $referrer->wallet->referral_balance : 0,
                    ];
                });

            // Commission analytics from wallet
            $commissionStats = [
                'total_referral_balance' => Wallet::sum('referral_balance'),
                'total_shopping_balance' => Wallet::sum('shopping_balance'),
                'total_reward_balance' => Wallet::sum('reward_balance'),
                'total_loyalty_points' => Wallet::sum('loyality_points'),
            ];

            return ResponseHelper::success([
                'referral_trends' => $referralTrends,
                'top_referrers' => $topReferrers,
                'commission_stats' => $commissionStats,
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
     * Update referral status - Not applicable in this referral system
     * This method is disabled as the referral system doesn't use status tracking
     */
    public function updateReferralStatus(Request $request, $referralId)
    {
        return ResponseHelper::error('Referral status updates are not supported in this referral system', 400);
    }

    /**
     * Bulk update referral status - Not applicable in this referral system
     * This method is disabled as the referral system doesn't use status tracking
     */
    public function bulkUpdateReferralStatus(Request $request)
    {
        return ResponseHelper::error('Bulk referral status updates are not supported in this referral system', 400);
    }

    /**
     * Get referral FAQs
     */
    public function getReferralFaqs()
    {
        try {
            $faqs = ReferralFaq::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return ResponseHelper::success($faqs);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create referral FAQ
     */
    public function createReferralFaq(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'question' => 'required|string|max:500',
                'answer' => 'required|string|max:2000',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error('Validation failed: ' . $validator->errors()->first(), 422);
            }

            $faq = ReferralFaq::create([
                'question' => $request->question,
                'answer' => $request->answer,
                'is_active' => $request->get('is_active', true),
            ]);

            return ResponseHelper::success($faq, 'Referral FAQ created successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update referral FAQ
     */
    public function updateReferralFaq(Request $request, $faqId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'question' => 'required|string|max:500',
                'answer' => 'required|string|max:2000',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error('Validation failed: ' . $validator->errors()->first(), 422);
            }

            $faq = ReferralFaq::findOrFail($faqId);
            $faq->update([
                'question' => $request->question,
                'answer' => $request->answer,
                'is_active' => $request->get('is_active', $faq->is_active),
            ]);

            return ResponseHelper::success($faq, 'Referral FAQ updated successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete referral FAQ
     */
    public function deleteReferralFaq($faqId)
    {
        try {
            $faq = ReferralFaq::findOrFail($faqId);
            $faq->delete();

            return ResponseHelper::success(null, 'Referral FAQ deleted successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Export referral data
     */
    public function exportReferralData(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            $referrals = User::with(['referrer'])
                ->whereNotNull('referral_code')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->get()
                ->map(function ($referredUser) {
                    return [
                        'referrer_name' => $referredUser->referrer ? $referredUser->referrer->full_name : 'N/A',
                        'referrer_email' => $referredUser->referrer ? $referredUser->referrer->email : 'N/A',
                        'referrer_code' => $referredUser->referrer ? $referredUser->referrer->user_code : 'N/A',
                        'referred_user_name' => $referredUser->full_name,
                        'referred_user_email' => $referredUser->email,
                        'referred_user_role' => $referredUser->role,
                        'created_at' => $referredUser->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return ResponseHelper::success([
                'referrals' => $referrals,
                'export_info' => [
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ],
                    'total_records' => $referrals->count(),
                    'exported_at' => now()->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update referral earnings for a user
     */
    private function updateReferralEarnings($userId, $amount)
    {
        // Update user's wallet referral balance
        $wallet = Wallet::where('user_id', $userId)->first();
        if ($wallet) {
            $wallet->increment('referral_balance', $amount);
        } else {
            // Create wallet if it doesn't exist
            Wallet::create([
                'user_id' => $userId,
                'shopping_balance' => 0,
                'reward_balance' => 0,
                'referral_balance' => $amount,
                'loyality_points' => 0,
            ]);
        }
    }
}
