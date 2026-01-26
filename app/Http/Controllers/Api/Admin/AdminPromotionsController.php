<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\BoostProduct;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPromotionsController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get all boosted products (promotions) with filtering and pagination
     */
    public function getAllPromotions(Request $request)
    {
        try {
            $query = BoostProduct::with(['product.images', 'store.user']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
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
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('store', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $promotions = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics with period filtering
            $totalPromotionsQuery = BoostProduct::query();
            $activePromotionsQuery = BoostProduct::where('status', 'active');
            $pendingPromotionsQuery = BoostProduct::where('status', 'pending');
            $completedPromotionsQuery = BoostProduct::where('status', 'completed');
            
            if ($period) {
                $this->applyPeriodFilter($totalPromotionsQuery, $period);
                $this->applyPeriodFilter($activePromotionsQuery, $period);
                $this->applyPeriodFilter($pendingPromotionsQuery, $period);
                $this->applyPeriodFilter($completedPromotionsQuery, $period);
            }
            
            $stats = [
                'total_promotions' => $totalPromotionsQuery->count(),
                'active_promotions' => $activePromotionsQuery->count(),
                'pending_promotions' => $pendingPromotionsQuery->count(),
                'completed_promotions' => $completedPromotionsQuery->count(),
                'total_revenue' => $activePromotionsQuery->sum('total_amount'),
                'total_impressions' => $activePromotionsQuery->sum('impressions'),
                'total_clicks' => $activePromotionsQuery->sum('clicks'),
            ];

            return ResponseHelper::success([
                'promotions' => $this->formatPromotionsData($promotions),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $promotions->currentPage(),
                    'last_page' => $promotions->lastPage(),
                    'per_page' => $promotions->perPage(),
                    'total' => $promotions->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed promotion information
     */
    public function getPromotionDetails($promotionId)
    {
        try {
            $promotion = BoostProduct::with([
                'product.images',
                'store.user',
                'product.variants',
                'product.reviews'
            ])->findOrFail($promotionId);

            $promotionData = [
                'promotion_info' => [
                    'id' => $promotion->id,
                    'status' => $promotion->status,
                    'start_date' => $promotion->start_date,
                    'duration' => $promotion->duration,
                    'budget' => $promotion->budget,
                    'total_amount' => $promotion->total_amount,
                    'location' => $promotion->location,
                    'reach' => $promotion->reach,
                    'impressions' => $promotion->impressions,
                    'cpc' => $promotion->cpc,
                    'clicks' => $promotion->clicks,
                    'payment_method' => $promotion->payment_method,
                    'payment_status' => $promotion->payment_status,
                    'created_at' => $promotion->created_at,
                ],
                'product_info' => [
                    'product'=>$promotion->product,
                    'product_images'=>$promotion->product->images,
                    'product_variants'=>$promotion->product->variants,
                    'product_reviews'=>$promotion->product->reviews,
                ] ,
                'store_info' => $promotion->store ? [
                    'store_id' => $promotion->store->id,
                    'store_name' => $promotion->store->store_name,
                    'seller_name' => $promotion->store->user?->full_name,
                    'seller_email' => $promotion->store->user?->email,
                ] : null,
                'performance_metrics' => [
                    'reach' => $promotion->reach,
                    'impressions' => $promotion->impressions,
                    'clicks' => $promotion->clicks,
                    'cpc' => $promotion->cpc,
                    'ctr' => $promotion->impressions > 0 ? ($promotion->clicks / $promotion->impressions) * 100 : 0,
                    'amount_spent' => $promotion->total_amount,
                    'remaining_budget' => $promotion->budget - $promotion->total_amount,
                ],
                'reviews_info' => $promotion->product?->reviews?->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'user_name' => $review->user?->full_name,
                        'created_at' => $review->created_at,
                    ];
                }) ?? [],
            ];

            return ResponseHelper::success($promotionData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update promotion status (approve, reject, stop)
     */
    public function updatePromotionStatus(Request $request, $promotionId)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,approved,active,stopped,rejected,completed',
                'notes' => 'nullable|string|max:500',
            ]);

            $promotion = BoostProduct::findOrFail($promotionId);
            $oldStatus = $promotion->status;
            
            $promotion->update([
                'status' => $request->status,
            ]);

            return ResponseHelper::success([
                'promotion_id' => $promotion->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'updated_at' => $promotion->updated_at,
            ], 'Promotion status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Extend promotion duration
     */
    public function extendPromotion(Request $request, $promotionId)
    {
        try {
            $request->validate([
                'additional_days' => 'required|integer|min:1|max:365',
                'additional_budget' => 'nullable|numeric|min:0',
            ]);

            $promotion = BoostProduct::findOrFail($promotionId);
            
            $newDuration = $promotion->duration + $request->additional_days;
            $newBudget = $promotion->budget + ($request->additional_budget ?? 0);
            
            $promotion->update([
                'duration' => $newDuration,
                'budget' => $newBudget,
            ]);

            return ResponseHelper::success([
                'promotion_id' => $promotion->id,
                'new_duration' => $newDuration,
                'new_budget' => $newBudget,
                'additional_days' => $request->additional_days,
                'additional_budget' => $request->additional_budget,
            ], 'Promotion extended successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on promotions
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|in:approve,reject,stop,extend',
                'promotion_ids' => 'required|array|min:1',
                'promotion_ids.*' => 'integer|exists:boost_products,id',
                'additional_days' => 'required_if:action,extend|integer|min:1|max:365',
                'additional_budget' => 'nullable|numeric|min:0',
            ]);

            $promotionIds = $request->promotion_ids;
            $action = $request->action;

            switch ($action) {
                case 'approve':
                    BoostProduct::whereIn('id', $promotionIds)->update(['status' => 'approved']);
                    return ResponseHelper::success(null, 'Promotions approved successfully');
                
                case 'reject':
                    BoostProduct::whereIn('id', $promotionIds)->update(['status' => 'rejected']);
                    return ResponseHelper::success(null, 'Promotions rejected successfully');
                
                case 'stop':
                    BoostProduct::whereIn('id', $promotionIds)->update(['status' => 'stopped']);
                    return ResponseHelper::success(null, 'Promotions stopped successfully');
                
                case 'extend':
                    $additionalDays = $request->additional_days;
                    $additionalBudget = $request->additional_budget ?? 0;
                    
                    BoostProduct::whereIn('id', $promotionIds)->update([
                        'duration' => DB::raw("duration + {$additionalDays}"),
                        'budget' => DB::raw("budget + {$additionalBudget}"),
                    ]);
                    return ResponseHelper::success(null, "Promotions extended by {$additionalDays} days");
            }
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get promotion statistics
     */
    public function getPromotionStatistics()
    {
        try {
            $stats = [
                'total_promotions' => BoostProduct::count(),
                'active_promotions' => BoostProduct::where('status', 'active')->count(),
                'pending_promotions' => BoostProduct::where('status', 'pending')->count(),
                'approved_promotions' => BoostProduct::where('status', 'approved')->count(),
                'rejected_promotions' => BoostProduct::where('status', 'rejected')->count(),
                'stopped_promotions' => BoostProduct::where('status', 'stopped')->count(),
                'completed_promotions' => BoostProduct::where('status', 'completed')->count(),
                'total_revenue' => BoostProduct::where('status', 'active')->sum('total_amount'),
                'total_impressions' => BoostProduct::where('status', 'active')->sum('impressions'),
                'total_clicks' => BoostProduct::where('status', 'active')->sum('clicks'),
                'average_cpc' => BoostProduct::where('status', 'active')->avg('cpc'),
            ];

            // Monthly trends
            $monthlyStats = BoostProduct::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total_promotions,
                SUM(total_amount) as total_revenue,
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks
            ')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            return ResponseHelper::success([
                'current_stats' => $stats,
                'monthly_trends' => $monthlyStats,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format promotions data for response
     */
    private function formatPromotionsData($promotions)
    {
        return $promotions->map(function ($promotion) {
            return [
                'id' => $promotion->id,
                'product_name' => $promotion->product?->name,
                'product_image' => $promotion->product?->images?->first() ? 
                    asset('storage/' . $promotion->product->images->first()->path) : null,
                'store_name' => $promotion->store?->store_name,
                'seller_name' => $promotion->store?->user?->full_name,
                'amount' => $promotion->total_amount,
                'duration' => $promotion->duration,
                'status' => $promotion->status,
                'reach' => $promotion->reach,
                'impressions' => $promotion->impressions,
                'clicks' => $promotion->clicks,
                'cpc' => $promotion->cpc,
                'created_at' => $promotion->created_at,
                'formatted_date' => $promotion->created_at->format('d-m-Y H:i A'),
                'status_color' => $this->getStatusColor($promotion->status),
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
            'approved' => 'blue',
            'pending' => 'yellow',
            'rejected' => 'red',
            'stopped' => 'gray',
            'completed' => 'purple',
            default => 'blue'
        };
    }
}
