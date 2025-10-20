<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStat;
use App\Models\BoostProduct;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProductsController extends Controller
{
    /**
     * Get all products with filtering and pagination
     */
    public function getAllProducts(Request $request)
    {
        try {
            $query = Product::with(['store.user', 'images', 'variants', 'reviews', 'boost']);

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
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('store', function ($storeQuery) use ($search) {
                          $storeQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $products = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_products' => Product::count(),
                'general_products' => Product::whereDoesntHave('boost', function ($q) {
                    $q->where('status', 'active');
                })->count(),
                'sponsored_products' => Product::whereHas('boost', function ($q) {
                    $q->where('status', 'active');
                })->count(),
                'active_products' => Product::where('status', 'active')->count(),
                'inactive_products' => Product::where('status', 'inactive')->count(),
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
            $product = Product::with([
                'store.user',
                'images',
                'variants',
                'reviews.user',
                'boost',
                'productStats'
            ])->findOrFail($productId);

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
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ],
                'store_info' => [
                    'store_id' => $product->store->id,
                    'store_name' => $product->store->store_name,
                    'seller_name' => $product->store->user->full_name,
                    'seller_email' => $product->store->user->email,
                    'store_location' => $product->store->store_location,
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
                        'user_name' => $review->user->full_name,
                        'created_at' => $review->created_at,
                        'formatted_date' => $review->created_at->format('d-m-Y H:i A'),
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
            ]);

            $product = Product::findOrFail($productId);
            
            $product->update([
                'status' => $request->status,
                'is_sold' => $request->get('is_sold', $product->is_sold),
                'is_unavailable' => $request->get('is_unavailable', $product->is_unavailable),
            ]);

            return ResponseHelper::success([
                'product_id' => $product->id,
                'status' => $product->status,
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

            $product = Product::with('store')->findOrFail($productId);

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

            $product = Product::findOrFail($productId);
            
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
            $product = Product::findOrFail($productId);
            
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
                'seller_name' => $product->store->user->full_name,
                'status' => $product->status,
                'is_sold' => $product->is_sold,
                'is_unavailable' => $product->is_unavailable,
                'is_sponsored' => $isSponsored,
                'quantity' => $product->quantity,
                'reviews_count' => $product->reviews->count(),
                'average_rating' => $product->reviews->avg('rating') ?? 0,
                'created_at' => $product->created_at,
                'formatted_date' => $product->created_at->format('d-m-Y H:i A'),
                'primary_image' => $product->images->first() ? 
                    asset('storage/' . $product->images->first()->path) : null,
            ];
        });
    }
}
