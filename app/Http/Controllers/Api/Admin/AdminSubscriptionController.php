<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSubscriptionController extends Controller
{
    /**
     * Get all subscriptions with filtering and pagination
     */
    public function getAllSubscriptions(Request $request)
    {
        try {
            $query = Subscription::with(['store.user', 'plan']);

            // Apply filters
            if ($request->has('plan_type') && $request->plan_type !== 'all') {
                $query->whereHas('plan', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->plan_type}%");
                });
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
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
                $query->whereHas('store', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('store.user', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $subscriptions = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_subscriptions' => Subscription::count(),
                'active_subscriptions' => Subscription::where('status', 'active')->count(),
                'expired_subscriptions' => Subscription::where('status', 'expired')->count(),
                'cancelled_subscriptions' => Subscription::where('status', 'cancelled')->count(),
                'total_revenue' => Subscription::where('status', 'active')
                    ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
                    ->sum('subscription_plans.price'),
                'basic_plan_stores' => Subscription::whereHas('plan', function ($q) {
                    $q->where('name', 'like', '%basic%');
                })->where('status', 'active')->count(),
                'standard_plan_stores' => Subscription::whereHas('plan', function ($q) {
                    $q->where('name', 'like', '%standard%');
                })->where('status', 'active')->count(),
                'ultra_plan_stores' => Subscription::whereHas('plan', function ($q) {
                    $q->where('name', 'like', '%ultra%');
                })->where('status', 'active')->count(),
            ];

            return ResponseHelper::success([
                'subscriptions' => $this->formatSubscriptionsData($subscriptions),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get subscription details
     */
    public function getSubscriptionDetails($subscriptionId)
    {
        try {
            $subscription = Subscription::with(['store.user', 'plan'])->findOrFail($subscriptionId);

            $subscriptionData = [
                'subscription_info' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'payment_method' => $subscription->payment_method,
                    'payment_status' => $subscription->payment_status,
                    'transaction_ref' => $subscription->transaction_ref,
                    'created_at' => $subscription->created_at,
                ],
                'store_info' => [
                    'store_id' => $subscription->store->id,
                    'store_name' => $subscription->store->store_name,
                    'owner_name' => $subscription->store->user->full_name,
                    'owner_email' => $subscription->store->user->email,
                ],
                'plan_info' => [
                    'plan_id' => $subscription->plan->id,
                    'plan_name' => $subscription->plan->name,
                    'price' => $subscription->plan->price,
                    'currency' => $subscription->plan->currency,
                    'duration_days' => $subscription->plan->duration_days,
                    'features' => $subscription->plan->features,
                ],
                'days_remaining' => $subscription->end_date ? now()->diffInDays($subscription->end_date, false) : null,
            ];

            return ResponseHelper::success($subscriptionData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get all subscription plans
     */
    public function getAllPlans()
    {
        try {
            $plans = SubscriptionPlan::withCount('subscriptions')->get();

            return ResponseHelper::success([
                'plans' => $plans->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => $plan->price,
                        'currency' => $plan->currency,
                        'duration_days' => $plan->duration_days,
                        'features' => $plan->features,
                        'active_subscriptions_count' => $plan->subscriptions_count,
                        'created_at' => $plan->created_at,
                    ];
                })
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create new subscription plan
     */
    public function createPlan(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:100',
                'price' => 'required|numeric|min:0',
                'currency' => 'required|string|max:3',
                'duration_days' => 'required|integer|min:1',
                'features' => 'required|array|min:1',
                'features.*' => 'string|max:255',
            ]);

            $plan = SubscriptionPlan::create([
                'name' => $request->name,
                'price' => $request->price,
                'currency' => $request->currency,
                'duration_days' => $request->duration_days,
                'features' => $request->features,
            ]);

            return ResponseHelper::success([
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'duration_days' => $plan->duration_days,
                    'features' => $plan->features,
                ]
            ], 'Subscription plan created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update subscription plan
     */
    public function updatePlan(Request $request, $planId)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:100',
                'price' => 'required|numeric|min:0',
                'currency' => 'required|string|max:3',
                'duration_days' => 'required|integer|min:1',
                'features' => 'required|array|min:1',
                'features.*' => 'string|max:255',
            ]);

            $plan = SubscriptionPlan::findOrFail($planId);
            $plan->update([
                'name' => $request->name,
                'price' => $request->price,
                'currency' => $request->currency,
                'duration_days' => $request->duration_days,
                'features' => $request->features,
            ]);

            return ResponseHelper::success([
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'duration_days' => $plan->duration_days,
                    'features' => $plan->features,
                ]
            ], 'Subscription plan updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete subscription plan
     */
    public function deletePlan($planId)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            
            // Check if plan has active subscriptions
            $activeSubscriptions = $plan->subscriptions()->where('status', 'active')->count();
            if ($activeSubscriptions > 0) {
                return ResponseHelper::error('Cannot delete plan with active subscriptions', 400);
            }

            $plan->delete();

            return ResponseHelper::success(null, 'Subscription plan deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update subscription status
     */
    public function updateSubscriptionStatus(Request $request, $subscriptionId)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,expired,cancelled,suspended',
                'notes' => 'nullable|string|max:500',
            ]);

            $subscription = Subscription::findOrFail($subscriptionId);
            $oldStatus = $subscription->status;
            
            $subscription->update([
                'status' => $request->status,
            ]);

            return ResponseHelper::success([
                'subscription_id' => $subscription->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'updated_at' => $subscription->updated_at,
            ], 'Subscription status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get subscription statistics
     */
    public function getSubscriptionStatistics()
    {
        try {
            $stats = [
                'total_subscriptions' => Subscription::count(),
                'active_subscriptions' => Subscription::where('status', 'active')->count(),
                'expired_subscriptions' => Subscription::where('status', 'expired')->count(),
                'cancelled_subscriptions' => Subscription::where('status', 'cancelled')->count(),
                'suspended_subscriptions' => Subscription::where('status', 'suspended')->count(),
                'total_revenue' => Subscription::where('status', 'active')
                    ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
                    ->sum('subscription_plans.price'),
            ];

            // Plan breakdown
            $planBreakdown = Subscription::selectRaw('
                subscription_plans.name as plan_name,
                COUNT(*) as subscription_count,
                SUM(subscription_plans.price) as total_revenue
            ')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.status', 'active')
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->get();

            // Monthly trends
            $monthlyStats = Subscription::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as new_subscriptions,
                SUM(subscription_plans.price) as monthly_revenue
            ')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            return ResponseHelper::success([
                'current_stats' => $stats,
                'plan_breakdown' => $planBreakdown,
                'monthly_trends' => $monthlyStats,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on subscriptions
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|in:update_status,activate,deactivate',
                'subscription_ids' => 'required|array|min:1',
                'subscription_ids.*' => 'integer|exists:subscriptions,id',
                'status' => 'required_if:action,update_status|in:active,expired,cancelled,suspended',
            ]);

            $subscriptionIds = $request->subscription_ids;
            $action = $request->action;

            switch ($action) {
                case 'update_status':
                    Subscription::whereIn('id', $subscriptionIds)->update(['status' => $request->status]);
                    return ResponseHelper::success(null, "Subscriptions status updated to {$request->status}");
                
                case 'activate':
                    Subscription::whereIn('id', $subscriptionIds)->update(['status' => 'active']);
                    return ResponseHelper::success(null, 'Subscriptions activated successfully');
                
                case 'deactivate':
                    Subscription::whereIn('id', $subscriptionIds)->update(['status' => 'suspended']);
                    return ResponseHelper::success(null, 'Subscriptions deactivated successfully');
            }
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format subscriptions data for response
     */
    private function formatSubscriptionsData($subscriptions)
    {
        return $subscriptions->map(function ($subscription) {
            $daysLeft = $subscription->end_date ? now()->diffInDays($subscription->end_date, false) : null;
            
            return [
                'id' => $subscription->id,
                'store_name' => $subscription->store->store_name,
                'owner_name' => $subscription->store->user->full_name,
                'plan_name' => $subscription->plan->name,
                'price' => $subscription->plan->price,
                'currency' => $subscription->plan->currency,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'days_left' => $daysLeft,
                'created_at' => $subscription->created_at,
                'formatted_date' => $subscription->created_at->format('d-m-Y H:i A'),
                'status_color' => $this->getStatusColor($subscription->status),
            ];
        });
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor($status)
    {
        return match($status) {
            'active' => 'green',
            'expired' => 'red',
            'cancelled' => 'gray',
            'suspended' => 'yellow',
            default => 'blue'
        };
    }
}
