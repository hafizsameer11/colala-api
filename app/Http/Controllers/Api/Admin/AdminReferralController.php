<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Models\ReferralFaq;
use App\Models\Wallet;
use App\Models\LoyaltySetting;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminReferralController extends Controller
{
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

            // Get referrers with their referral counts and earnings
            $referrers = User::whereHas('referrals')
                ->withCount('referrals')
                ->with(['referralEarning'])
                ->select('id', 'full_name', 'email', 'user_code', 'referral_code', 'role', 'created_at')
                ->paginate($request->get('per_page', 20));

            // Calculate amount earned for each referrer
            $referrers->getCollection()->transform(function ($referrer) {
                $referralEarning = $referrer->referralEarning;
                $referrer->amount_earned = $referralEarning ? $referralEarning->total_earned : 0;
                $referrer->current_balance = $referralEarning ? $referralEarning->current_balance : 0;
                $referrer->total_withdrawn = $referralEarning ? $referralEarning->total_withdrawn : 0;
                return $referrer;
            });

            // Get referral statistics
            $stats = [
                'total_referred' => $totalReferred,
                'today_referred' => $todayReferred,
                'sellers_referred' => $sellersReferred,
                'total_referrers' => User::whereHas('referrals')->count(),
                'total_commission_paid' => ReferralEarning::sum('total_earned'),
                'total_commission_withdrawn' => ReferralEarning::sum('total_withdrawn'),
                'pending_commission' => ReferralEarning::sum('current_balance'),
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
                ->with(['referralEarning'])
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
                        'total_earned' => $referrer->referralEarning ? $referrer->referralEarning->total_earned : 0,
                    ];
                });

            // Commission analytics
            $commissionStats = [
                'total_commission_paid' => ReferralEarning::sum('total_earned'),
                'total_commission_withdrawn' => ReferralEarning::sum('total_withdrawn'),
                'pending_commission' => ReferralEarning::sum('current_balance'),
                'average_commission_per_referral' => ReferralEarning::avg('total_earned'),
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
        $referralEarning = ReferralEarning::firstOrCreate(['user_id' => $userId]);
        
        $referralEarning->increment('total_earned', $amount);
        $referralEarning->increment('current_balance', $amount);
        
        // Update user's wallet referral balance
        $wallet = Wallet::where('user_id', $userId)->first();
        if ($wallet) {
            $wallet->increment('referral_balance', $amount);
        }
    }
}
