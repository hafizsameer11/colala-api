<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStat;
use App\Models\BoostProduct;
use App\Models\Store;
use App\Models\User;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProductsController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get all products with filtering and pagination
     */
    public function getAllProducts(Request $request)
    {
        try {
            $query = Product::with(['store.user', 'images', 'variants', 'reviews', 'boost', 'category']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                switch ($request->status) {
                    case 'general':
                        $query->whereDoesntHave('boost', function ($q) {
                            $q->where('status', 'active');
                        });
                        break;
                    case 'sponsored':
                        $query->whereHas('boost', function ($q) {
                            $q->where('status', 'active');
                        });
                        break;
                    case 'active':
                        $query->where('status', 'active');
                        break;
                    case 'inactive':
                        $query->where('status', 'inactive');
                        break;
                }
            }

            if ($request->has('category') && $request->category !== 'all') {
                $query->where('category_id', $request->category);
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
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('store', function ($storeQuery) use ($search) {
                          $storeQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $products = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics with period filtering
            $totalProductsQuery = Product::query();
            $generalProductsQuery = Product::whereDoesntHave('boost', function ($q) {
                $q->where('status', 'active');
            });
            $sponsoredProductsQuery = Product::whereHas('boost', function ($q) {
                $q->where('status', 'active');
            });
            $activeProductsQuery = Product::where('status', 'active');
            $inactiveProductsQuery = Product::where('status', 'inactive');
            
            if ($period) {
                $this->applyPeriodFilter($totalProductsQuery, $period);
                $this->applyPeriodFilter($generalProductsQuery, $period);
                $this->applyPeriodFilter($sponsoredProductsQuery, $period);
                $this->applyPeriodFilter($activeProductsQuery, $period);
                $this->applyPeriodFilter($inactiveProductsQuery, $period);
            }
            
            $stats = [
                'total_products' => $totalProductsQuery->count(),
                'general_products' => $generalProductsQuery->count(),
                'sponsored_products' => $sponsoredProductsQuery->count(),
                'active_products' => $activeProductsQuery->count(),
                'inactive_products' => $inactiveProductsQuery->count(),
            ];

            return ResponseHelper::success([
                'products' => $this->formatProductsData($products),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed product information
     */
    public function getProductDetails($productId)
    {
        try {
            // Use withoutGlobalScopes() to bypass ALL global scopes including visibility filter
            // Query explicitly to ensure scope is bypassed
            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->with([
                    'store' => function ($q) {
                        $q->withoutGlobalScopes();
                    },
                    'store.user' => function ($q) {
                        $q->withoutGlobalScopes();
                    },
                    'images',
                    'variants',
                    'reviews.user' => function ($q) {
                        $q->withoutGlobalScopes();
                    },
                    'boost',
                    'productStats',
                    'category'
                ])->first();
            
            if (!$product) {
                return ResponseHelper::error('Product not found', 404);
            }

            $productData = [
                'product_info' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount_price' => $product->discount_price,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'is_sold' => $product->is_sold,
                    'is_unavailable' => $product->is_unavailable,
                    'brand' => $product->brand ?? null,
                    'tag1' => $product->tag1 ?? null,
                    'tag2' => $product->tag2 ?? null,
                    'tag3' => $product->tag3 ?? null,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'video' => $product->video,
                ],
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->title ?? $product->category->name ?? null,
                    'title' => $product->category->title ?? null,
                ] : null,
                'store_info' => [
                    'store_id' => $product->store ? $product->store->id : null,
                    'store_name' => $product->store ? $product->store->store_name : null,
                    'seller_name' => $product->store ? $product->store->store_name : null,
                    'seller_email' => $product->store && $product->store->user ? $product->store->user->email : null,
                    'seller_user_id' => $product->store && $product->store->user ? $product->store->user->id : null,
                    'store_location' => $product->store ? $product->store->store_location : null,
                    'profile_image' => $product->store && $product->store->profile_image ? asset('storage/' . $product->store->profile_image) : null,
                    'banner_image' => $product->store && $product->store->banner_image ? asset('storage/' . $product->store->banner_image) : null,
                ],
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => asset('storage/' . $image->path),
                    ];
                }),
                'variants' => $product->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'color' => $variant->color,
                        'size' => $variant->size,
                        'price' => $variant->price,
                        'stock' => $variant->stock,
                    ];
                }),
                'reviews' => $product->reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'user_name' => $review->user ? $review->user->full_name : 'Unknown User',
                        'created_at' => $review->created_at,
                        'formatted_date' => $review->created_at ? $review->created_at->format('d-m-Y H:i A') : null,
                    ];
                }),
                'boost_info' => $product->boost ? [
                    'id' => $product->boost->id,
                    'status' => $product->boost->status,
                    'start_date' => $product->boost->start_date,
                    'duration' => $product->boost->duration,
                    'budget' => $product->boost->budget,
                    'total_amount' => $product->boost->total_amount,
                    'reach' => $product->boost->reach,
                    'impressions' => $product->boost->impressions,
                    'clicks' => $product->boost->clicks,
                ] : null,
                'statistics' => $this->getProductStatistics($product),
            ];

            return ResponseHelper::success($productData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update product status
     */
    public function updateProductStatus(Request $request, $productId)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive',
                'is_sold' => 'boolean',
                'is_unavailable' => 'boolean',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);

            $product = Product::withoutGlobalScopes()
                ->with(['store.user'])
                ->findOrFail($productId);
            
            $updateData = [
                'status' => $request->status,
                'is_sold' => $request->get('is_sold', $product->is_sold),
                'is_unavailable' => $request->get('is_unavailable', $product->is_unavailable),
            ];

            // If status is inactive and rejection_reason is provided, save it
            if ($request->status === 'inactive' && $request->has('rejection_reason')) {
                $updateData['rejection_reason'] = $request->rejection_reason;
            } elseif ($request->status === 'active') {
                // Clear rejection reason when product is activated
                $updateData['rejection_reason'] = null;
            }

            $product->update($updateData);

            // Send notification to seller if product is marked as inactive
            if ($request->status === 'inactive' && $product->store && $product->store->user) {
                $seller = $product->store->user;
                $rejectionReason = $request->rejection_reason ?? 'No reason provided';
                
                $title = 'Product Rejected';
                $message = "Your product '{$product->name}' has been marked as inactive.";
                
                if ($request->rejection_reason) {
                    $message .= "\n\nReason: {$rejectionReason}";
                }

                try {
                    // Log seller info for debugging
                    Log::info('Attempting to send product rejection notification', [
                        'product_id' => $product->id,
                        'seller_id' => $seller->id,
                        'seller_email' => $seller->email,
                        'has_expo_token' => !empty($seller->expo_push_token),
                        'expo_token_length' => $seller->expo_push_token ? strlen($seller->expo_push_token) : 0,
                        'rejection_reason' => $rejectionReason
                    ]);

                    UserNotificationHelper::notify(
                        $seller->id,
                        $title,
                        $message,
                        [
                            'type' => 'product_rejected',
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'rejection_reason' => $rejectionReason,
                        ]
                    );

                    Log::info('Product rejection notification sent to seller', [
                        'product_id' => $product->id,
                        'seller_id' => $seller->id,
                        'seller_email' => $seller->email,
                        'has_expo_token' => !empty($seller->expo_push_token),
                        'rejection_reason' => $rejectionReason
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send product rejection notification', [
                        'product_id' => $product->id,
                        'seller_id' => $seller->id,
                        'seller_email' => $seller->email,
                        'has_expo_token' => !empty($seller->expo_push_token),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Don't fail the request if notification fails
                }
            }

            return ResponseHelper::success([
                'product_id' => $product->id,
                'status' => $product->status,
                'rejection_reason' => $product->rejection_reason,
                'is_sold' => $product->is_sold,
                'is_unavailable' => $product->is_unavailable,
                'updated_at' => $product->updated_at,
            ], 'Product status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Boost product
     */
    public function boostProduct(Request $request, $productId)
    {
        try {
            $request->validate([
                'duration' => 'required|integer|min:1|max:365',
                'budget' => 'required|numeric|min:0',
                'location' => 'nullable|string|max:255',
            ]);

            $product = Product::withoutGlobalScopes()->with([
                'store' => function ($q) {
                    $q->withoutGlobalScopes();
                }
            ])->findOrFail($productId);

            // Check if product already has active boost
            $existingBoost = BoostProduct::where('product_id', $productId)
                ->where('status', 'active')
                ->first();

            if ($existingBoost) {
                return ResponseHelper::error('Product already has an active boost', 400);
            }

            $boostProduct = BoostProduct::create([
                'product_id' => $product->id,
                'store_id' => $product->store->id,
                'start_date' => now(),
                'duration' => $request->duration,
                'budget' => $request->budget,
                'location' => $request->location,
                'status' => 'pending',
                'total_amount' => 0,
                'reach' => 0,
                'impressions' => 0,
                'clicks' => 0,
                'cpc' => 0,
                'payment_method' => 'wallet',
                'payment_status' => 'pending',
            ]);

            return ResponseHelper::success([
                'boost_id' => $boostProduct->id,
                'product_id' => $product->id,
                'duration' => $request->duration,
                'budget' => $request->budget,
                'status' => 'pending',
            ], 'Product boost created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update product information
     */
    public function updateProduct(Request $request, $productId)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'discount_price' => 'nullable|numeric|min:0',
                'quantity' => 'required|integer|min:0',
                'status' => 'required|in:active,inactive',
            ]);

            $product = Product::withoutGlobalScopes()->findOrFail($productId);
            
            $product->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'discount_price' => $request->discount_price,
                'quantity' => $request->quantity,
                'status' => $request->status,
            ]);

            return ResponseHelper::success([
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'updated_at' => $product->updated_at,
            ], 'Product updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete product
     */
    public function deleteProduct($productId)
    {
        try {
            $product = Product::withoutGlobalScopes()->findOrFail($productId);
            
            // Delete related data
            $product->images()->delete();
            $product->variants()->delete();
            $product->reviews()->delete();
            $product->productStats()->delete();
            $product->boost()->delete();
            $product->delete();

            return ResponseHelper::success(null, 'Product deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get product statistics
     */
    private function getProductStatistics($product)
    {
        $stats = $product->statsSummary();
        
        return [
            'views' => $stats['view'] ?? 0,
            'impressions' => $stats['impression'] ?? 0,
            'clicks' => $stats['click'] ?? 0,
            'chats' => $stats['chat'] ?? 0,
            'phone_views' => $stats['phone_view'] ?? 0,
            'total_engagement' => array_sum($stats),
            'average_rating' => $product->reviews->avg('rating') ?? 0,
            'total_reviews' => $product->reviews->count(),
            'boost_count' => $product->boost ? 1 : 0,
            'active_boost' => $product->boost && $product->boost->status === 'active',
        ];
    }

    /**
     * Get product analytics
     */
    public function getProductAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Top performing products
            $topProducts = Product::with(['store', 'images'])
                ->selectRaw('
                    products.*,
                    (SELECT COUNT(*) FROM product_stats WHERE product_id = products.id AND event_type = "view") as views,
                    (SELECT COUNT(*) FROM product_stats WHERE product_id = products.id AND event_type = "click") as clicks,
                    (SELECT COUNT(*) FROM product_reviews WHERE product_id = products.id) as reviews_count,
                    (SELECT AVG(rating) FROM product_reviews WHERE product_id = products.id) as avg_rating
                ')
                ->orderByDesc('views')
                ->limit(10)
                ->get();

            // Category breakdown
            $categoryStats = Product::selectRaw('
                categories.name as category_name,
                COUNT(*) as product_count,
                AVG(products.price) as avg_price
            ')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name')
            ->get();

            return ResponseHelper::success([
                'top_products' => $topProducts,
                'category_breakdown' => $categoryStats,
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
     * Format products data for response
     */
    private function formatProductsData($products)
    {
        return $products->map(function ($product) {
            $isSponsored = $product->boost && $product->boost->status === 'active';
            
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'discount_price' => $product->discount_price,
                'store_name' => $product->store->store_name,
                'seller_name' => $product->store->store_name,
                'status' => $product->status,
                'is_sold' => $product->is_sold,
                'is_unavailable' => $product->is_unavailable,
                'is_sponsored' => $isSponsored,
                'quantity' => $product->quantity,
                'reviews_count' => $product->reviews->count(),
                'average_rating' => $product->reviews->avg('rating') ?? 0,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'title' => $product->category->title,
                    'image' => $product->category->image,
                    'color' => $product->category->color,
                    'image_url' => $product->category->image_url ?? null,
                ] : null,
                'created_at' => $product->created_at,
                'formatted_date' => $product->created_at ? $product->created_at->format('d-m-Y H:i A') : null,
                'primary_image' => $product->images->first() ? 
                    asset('storage/' . $product->images->first()->path) : null,
            ];
        });
    }
}
