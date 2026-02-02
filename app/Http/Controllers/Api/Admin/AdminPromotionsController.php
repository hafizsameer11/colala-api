<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\BoostProduct;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            // Apply filters (map frontend status to DB enum if needed)
            if ($request->has('status') && $request->status !== 'all') {
                $statusMapping = [
                    'approved' => 'scheduled',
                    'active' => 'running',
                    'stopped' => 'paused',
                    'rejected' => 'cancelled',
                ];
                $dbStatus = $statusMapping[$request->status] ?? $request->status;
                $query->where('status', $dbStatus);
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

            // Check if export is requested
            if ($request->has('export') && $request->export == 'true') {
                $promotions = $query->latest()->get();
                return ResponseHelper::success($promotions, 'Promotions exported successfully');
            }

            $promotions = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics with period filtering
            $totalPromotionsQuery = BoostProduct::query();
            $activePromotionsQuery = BoostProduct::where('status', 'running'); // active -> running
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
                    'status' => $this->mapDbStatusToFrontend($promotion->status),
                    'db_status' => $promotion->status, // Include raw DB status for reference
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
     * Maps frontend status values to database enum values
     */
    public function updatePromotionStatus(Request $request, $promotionId)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,approved,active,stopped,rejected,completed,draft,scheduled,running,paused,cancelled',
                'notes' => 'nullable|string|max:500',
            ]);

            $promotion = BoostProduct::with(['store.user', 'product'])->findOrFail($promotionId);
            $oldStatus = $promotion->status;
            
            // Map frontend status values to database enum values
            $statusMapping = [
                'approved' => 'scheduled',  // approved -> scheduled (ready to run)
                'active' => 'running',      // active -> running
                'stopped' => 'paused',      // stopped -> paused
                'rejected' => 'cancelled',  // rejected -> cancelled
                // These already match database enum
                'pending' => 'pending',
                'completed' => 'completed',
                'draft' => 'draft',
                'scheduled' => 'scheduled',
                'running' => 'running',
                'paused' => 'paused',
                'cancelled' => 'cancelled',
            ];
            
            $dbStatus = $statusMapping[$request->status] ?? $request->status;
            
            $promotion->update([
                'status' => $dbStatus,
            ]);

            // Send notification to seller when promotion is approved
            if ($request->status === 'approved' && $promotion->store && $promotion->store->user) {
                try {
                    $productName = $promotion->product?->name ?? 'your product';
                    $notesText = $request->notes ? " Note: {$request->notes}" : '';
                    
                    UserNotificationHelper::notify(
                        $promotion->store->user->id,
                        'Promotion Approved',
                        "Your product promotion for '{$productName}' has been approved and is now scheduled.{$notesText}",
                        [
                            'type' => 'promotion_approved',
                            'promotion_id' => $promotion->id,
                            'product_id' => $promotion->product_id,
                            'store_id' => $promotion->store_id,
                            'status' => $request->status,
                            'notes' => $request->notes
                        ]
                    );
                } catch (\Exception $e) {
                    // Log error but don't fail the status update
                    Log::error('Failed to send promotion approval notification: ' . $e->getMessage(), [
                        'promotion_id' => $promotionId,
                        'seller_id' => $promotion->store->user->id ?? null
                    ]);
                }
            }

            // Send notification to seller when promotion is rejected
            if ($request->status === 'rejected' && $promotion->store && $promotion->store->user) {
                try {
                    $productName = $promotion->product?->name ?? 'your product';
                    $notesText = $request->notes ? " Reason: {$request->notes}" : '';
                    
                    UserNotificationHelper::notify(
                        $promotion->store->user->id,
                        'Promotion Rejected',
                        "Your product promotion for '{$productName}' has been rejected.{$notesText}",
                        [
                            'type' => 'promotion_rejected',
                            'promotion_id' => $promotion->id,
                            'product_id' => $promotion->product_id,
                            'store_id' => $promotion->store_id,
                            'status' => $request->status,
                            'notes' => $request->notes
                        ]
                    );
                } catch (\Exception $e) {
                    // Log error but don't fail the status update
                    Log::error('Failed to send promotion rejection notification: ' . $e->getMessage(), [
                        'promotion_id' => $promotionId,
                        'seller_id' => $promotion->store->user->id ?? null
                    ]);
                }
            }

            return ResponseHelper::success([
                'promotion_id' => $promotion->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status, // Return frontend status for consistency
                'db_status' => $dbStatus, // Include actual DB status for debugging
                'updated_at' => $promotion->updated_at,
            ], 'Promotion status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update promotion details (general edit route)
     * Allows admin to edit: start_date, duration, budget, location, status, payment_method, payment_status
     */
    public function updatePromotion(Request $request, $promotionId)
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date|after_or_equal:today',
                'duration' => 'nullable|integer|min:1|max:365',
                'budget' => 'nullable|numeric|min:0',
                'location' => 'nullable|string|max:255',
                'status' => 'nullable|in:pending,approved,active,stopped,rejected,completed,draft,scheduled,running,paused,cancelled',
                'payment_method' => 'nullable|in:wallet,card,bank',
                'payment_status' => 'nullable|in:pending,paid,failed,refunded',
                'notes' => 'nullable|string|max:500', // Admin notes (not stored, just for notifications)
            ]);

            $promotion = BoostProduct::with(['store.user', 'product'])->findOrFail($promotionId);
            $oldStatus = $promotion->status;
            
            $updateData = [];
            
            // Update start_date if provided
            if ($request->has('start_date')) {
                $updateData['start_date'] = $request->start_date;
            }
            
            // Update duration if provided
            if ($request->has('duration')) {
                $updateData['duration'] = $request->duration;
            }
            
            // Update budget if provided
            if ($request->has('budget')) {
                $updateData['budget'] = $request->budget;
                // Recalculate total_amount if budget or duration changed
                // total_amount = budget * duration + platform_fee (simplified calculation)
                $duration = $updateData['duration'] ?? $promotion->duration;
                $budget = $updateData['budget'] ?? $promotion->budget;
                $platformFee = ($budget * $duration) * 0.1; // 10% platform fee
                $updateData['total_amount'] = ($budget * $duration) + $platformFee;
            } elseif ($request->has('duration')) {
                // If only duration changed, recalculate total_amount
                $duration = $request->duration;
                $budget = $promotion->budget ?? 0;
                $platformFee = ($budget * $duration) * 0.1;
                $updateData['total_amount'] = ($budget * $duration) + $platformFee;
            }
            
            // Update location if provided
            if ($request->has('location')) {
                $updateData['location'] = $request->location;
            }
            
            // Update status if provided (with mapping)
            if ($request->has('status')) {
                $statusMapping = [
                    'approved' => 'scheduled',
                    'active' => 'running',
                    'stopped' => 'paused',
                    'rejected' => 'cancelled',
                    'pending' => 'pending',
                    'completed' => 'completed',
                    'draft' => 'draft',
                    'scheduled' => 'scheduled',
                    'running' => 'running',
                    'paused' => 'paused',
                    'cancelled' => 'cancelled',
                ];
                $updateData['status'] = $statusMapping[$request->status] ?? $request->status;
            }
            
            // Update payment_method if provided
            if ($request->has('payment_method')) {
                $updateData['payment_method'] = $request->payment_method;
            }
            
            // Update payment_status if provided
            if ($request->has('payment_status')) {
                $updateData['payment_status'] = $request->payment_status;
            }
            
            // Update the promotion
            if (!empty($updateData)) {
                $promotion->update($updateData);
            }
            
            // Send notifications if status changed
            if ($request->has('status') && $request->status !== $this->mapDbStatusToFrontend($oldStatus)) {
                // Send notification to seller when promotion is approved
                if ($request->status === 'approved' && $promotion->store && $promotion->store->user) {
                    try {
                        $productName = $promotion->product?->name ?? 'your product';
                        $notesText = $request->notes ? " Note: {$request->notes}" : '';
                        
                        UserNotificationHelper::notify(
                            $promotion->store->user->id,
                            'Promotion Approved',
                            "Your product promotion for '{$productName}' has been approved and is now scheduled.{$notesText}",
                            [
                                'type' => 'promotion_approved',
                                'promotion_id' => $promotion->id,
                                'product_id' => $promotion->product_id,
                                'store_id' => $promotion->store_id,
                                'status' => $request->status,
                                'notes' => $request->notes
                            ]
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to send promotion approval notification: ' . $e->getMessage());
                    }
                }
                
                // Send notification to seller when promotion is rejected
                if ($request->status === 'rejected' && $promotion->store && $promotion->store->user) {
                    try {
                        $productName = $promotion->product?->name ?? 'your product';
                        $notesText = $request->notes ? " Reason: {$request->notes}" : '';
                        
                        UserNotificationHelper::notify(
                            $promotion->store->user->id,
                            'Promotion Rejected',
                            "Your product promotion for '{$productName}' has been rejected.{$notesText}",
                            [
                                'type' => 'promotion_rejected',
                                'promotion_id' => $promotion->id,
                                'product_id' => $promotion->product_id,
                                'store_id' => $promotion->store_id,
                                'status' => $request->status,
                                'notes' => $request->notes
                            ]
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to send promotion rejection notification: ' . $e->getMessage());
                    }
                }
            }
            
            // Refresh to get updated values
            $promotion->refresh();
            
            return ResponseHelper::success([
                'promotion_id' => $promotion->id,
                'updated_fields' => array_keys($updateData),
                'promotion' => [
                    'id' => $promotion->id,
                    'status' => $this->mapDbStatusToFrontend($promotion->status),
                    'db_status' => $promotion->status,
                    'start_date' => $promotion->start_date,
                    'duration' => $promotion->duration,
                    'budget' => $promotion->budget,
                    'total_amount' => $promotion->total_amount,
                    'location' => $promotion->location,
                    'payment_method' => $promotion->payment_method,
                    'payment_status' => $promotion->payment_status,
                    'updated_at' => $promotion->updated_at,
                ]
            ], 'Promotion updated successfully');
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
                    BoostProduct::whereIn('id', $promotionIds)->update(['status' => 'scheduled']);
                    return ResponseHelper::success(null, 'Promotions approved successfully');
                
                case 'reject':
                    BoostProduct::whereIn('id', $promotionIds)->update(['status' => 'cancelled']);
                    return ResponseHelper::success(null, 'Promotions rejected successfully');
                
                case 'stop':
                    BoostProduct::whereIn('id', $promotionIds)->update(['status' => 'paused']);
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
                'active_promotions' => BoostProduct::where('status', 'running')->count(), // active -> running
                'pending_promotions' => BoostProduct::where('status', 'pending')->count(),
                'approved_promotions' => BoostProduct::where('status', 'scheduled')->count(), // approved -> scheduled
                'rejected_promotions' => BoostProduct::where('status', 'cancelled')->count(), // rejected -> cancelled
                'stopped_promotions' => BoostProduct::where('status', 'paused')->count(), // stopped -> paused
                'completed_promotions' => BoostProduct::where('status', 'completed')->count(),
                'total_revenue' => BoostProduct::where('status', 'running')->sum('total_amount'), // active -> running
                'total_impressions' => BoostProduct::where('status', 'running')->sum('impressions'), // active -> running
                'total_clicks' => BoostProduct::where('status', 'running')->sum('clicks'), // active -> running
                'average_cpc' => BoostProduct::where('status', 'running')->avg('cpc'), // active -> running
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
                'status' => $this->mapDbStatusToFrontend($promotion->status),
                'db_status' => $promotion->status, // Include raw DB status for reference
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
     * Map database enum status to frontend-friendly status name
     */
    private function mapDbStatusToFrontend($dbStatus)
    {
        $statusMap = [
            'running' => 'active',
            'scheduled' => 'approved',
            'paused' => 'stopped',
            'cancelled' => 'rejected',
        ];
        
        return $statusMap[$dbStatus] ?? $dbStatus;
    }

    /**
     * Get status color for UI
     * Maps database enum values to frontend-friendly status names and colors
     */
    private function getStatusColor($status)
    {
        // Map DB enum values to frontend status names
        $statusMap = [
            'running' => 'active',
            'scheduled' => 'approved',
            'paused' => 'stopped',
            'cancelled' => 'rejected',
        ];
        
        $frontendStatus = $statusMap[$status] ?? $status;
        
        return match($frontendStatus) {
            'active', 'running' => 'green',
            'approved', 'scheduled' => 'blue',
            'pending' => 'yellow',
            'rejected', 'cancelled' => 'red',
            'stopped', 'paused' => 'gray',
            'completed' => 'purple',
            default => 'blue'
        };
    }
}
