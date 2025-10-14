<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\ProductStat;
use App\Models\BoostProduct;
use App\Http\Requests\ProductCreateUpdateRequest;
use App\Http\Requests\BoostProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SellerProductController extends Controller
{
    /**
     * Get all products for a specific seller
     */
    public function getSellerProducts(Request $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Product::with([
                'store:id,store_name,store_email,store_phone',
                'category:id,title,image',
                'images',
                'variants.images',
                'boost'
            ])
            ->withCount([
                'productStats as views' => fn($q) => $q->where('event_type', 'view'),
                'productStats as impressions' => fn($q) => $q->where('event_type', 'impression'),
                'productStats as clicks' => fn($q) => $q->where('event_type', 'click'),
                'productStats as carts' => fn($q) => $q->where('event_type', 'add_to_cart'),
                'productStats as orders' => fn($q) => $q->where('event_type', 'order'),
                'productStats as chats' => fn($q) => $q->where('event_type', 'chat'),
            ])
            ->where('store_id', $store->id);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category_id') && $request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by product type
            if ($request->has('type') && $request->type !== 'all') {
                if ($request->type === 'general') {
                    $query->whereDoesntHave('boost');
                } elseif ($request->type === 'sponsored') {
                    $query->whereHas('boost', function ($q) {
                        $q->where('status', 'active');
                    });
                }
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
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('brand', 'like', "%{$search}%");
                });
            }

            $products = $query->latest()->paginate(20);

            // Get summary statistics
            $allProducts = Product::where('store_id', $store->id)->count();
            $generalProducts = Product::where('store_id', $store->id)
                ->whereDoesntHave('boost')
                ->count();
            $sponsoredProducts = Product::where('store_id', $store->id)
                ->whereHas('boost', function ($q) {
                    $q->where('status', 'active');
                })
                ->count();

            $products->getCollection()->transform(function ($product) {
                $mainImage = $product->images->where('is_main', true)->first();
                $isSponsored = $product->boost && $product->boost->status === 'active';
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'description' => $product->description,
                    'price' => $product->price ? 'N' . number_format($product->price, 0) : null,
                    'discount_price' => $product->discount_price ? 'N' . number_format($product->discount_price, 0) : null,
                    'status' => $product->status,
                    'is_sold' => $product->is_sold,
                    'is_unavailable' => $product->is_unavailable,
                    'quantity' => $product->quantity,
                    'has_variants' => $product->has_variants,
                    'is_sponsored' => $isSponsored,
                    'sponsored_status' => $isSponsored ? 'active' : 'inactive',
                    'main_image' => $mainImage ? asset('storage/' . $mainImage->path) : null,
                    'store_name' => $product->store->store_name,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'title' => $product->category->title,
                        'image' => $product->category->image ? asset('storage/' . $product->category->image) : null
                    ] : null,
                    'stats' => [
                        'views' => $product->views,
                        'impressions' => $product->impressions,
                        'clicks' => $product->clicks,
                        'carts' => $product->carts,
                        'orders' => $product->orders,
                        'chats' => $product->chats
                    ],
                    'variants_count' => $product->variants->count(),
                    'images_count' => $product->images->count(),
                    'created_at' => $product->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $product->updated_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success([
                'products' => $products,
                'summary_stats' => [
                    'all_products' => [
                        'count' => $allProducts,
                        'increase' => 5, // Mock data
                        'color' => 'red'
                    ],
                    'general_products' => [
                        'count' => $generalProducts,
                        'increase' => 5, // Mock data
                        'color' => 'red'
                    ],
                    'sponsored_products' => [
                        'count' => $sponsoredProducts,
                        'increase' => 5, // Mock data
                        'color' => 'red'
                    ]
                ]
            ], 'Seller products retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed product information
     */
    public function getProductDetails($userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::with([
                'store:id,store_name,store_email,store_phone,store_location,profile_image,banner_image',
                'category:id,title,image,color',
                'images',
                'variants.images',
                'boost',
                'deliveryOptions',
                'reviews.user:id,full_name,profile_picture'
            ])
            ->withCount([
                'productStats as views' => fn($q) => $q->where('event_type', 'view'),
                'productStats as impressions' => fn($q) => $q->where('event_type', 'impression'),
                'productStats as clicks' => fn($q) => $q->where('event_type', 'click'),
                'productStats as carts' => fn($q) => $q->where('event_type', 'add_to_cart'),
                'productStats as orders' => fn($q) => $q->where('event_type', 'order'),
                'productStats as chats' => fn($q) => $q->where('event_type', 'chat'),
            ])
            ->where('store_id', $store->id)
            ->findOrFail($productId);

            $productDetails = [
                'product_info' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'description' => $product->description,
                    'price' => $product->price,
                    'formatted_price' => $product->price ? 'N' . number_format($product->price, 0) : null,
                    'discount_price' => $product->discount_price,
                    'formatted_discount_price' => $product->discount_price ? 'N' . number_format($product->discount_price, 0) : null,
                    'status' => $product->status,
                    'is_sold' => $product->is_sold,
                    'is_unavailable' => $product->is_unavailable,
                    'quantity' => $product->quantity,
                    'has_variants' => $product->has_variants,
                    'video' => $product->video ? asset('storage/' . $product->video) : null,
                    'coupon_code' => $product->coupon_code,
                    'discount' => $product->discount,
                    'loyality_points_applicable' => $product->loyality_points_applicable,
                    'average_rating' => $product->average_rating,
                    'created_at' => $product->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $product->updated_at->format('d-m-Y H:i:s')
                ],
                'store_info' => [
                    'id' => $product->store->id,
                    'name' => $product->store->store_name,
                    'email' => $product->store->store_email,
                    'phone' => $product->store->store_phone,
                    'location' => $product->store->store_location,
                    'profile_image' => $product->store->profile_image ? asset('storage/' . $product->store->profile_image) : null,
                    'banner_image' => $product->store->banner_image ? asset('storage/' . $product->store->banner_image) : null
                ],
                'category_info' => $product->category ? [
                    'id' => $product->category->id,
                    'title' => $product->category->title,
                    'image' => $product->category->image ? asset('storage/' . $product->category->image) : null,
                    'color' => $product->category->color
                ] : null,
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => asset('storage/' . $image->path),
                        'is_main' => $image->is_main,
                        'variant_id' => $image->variant_id
                    ];
                }),
                'variants' => $product->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'color' => $variant->color,
                        'size' => $variant->size,
                        'price' => $variant->price,
                        'formatted_price' => $variant->price ? 'N' . number_format($variant->price, 0) : null,
                        'discount_price' => $variant->discount_price,
                        'formatted_discount_price' => $variant->discount_price ? 'N' . number_format($variant->discount_price, 0) : null,
                        'stock' => $variant->stock,
                        'images' => $variant->images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'path' => asset('storage/' . $image->path)
                            ];
                        })
                    ];
                }),
                'boost_info' => $product->boost ? [
                    'id' => $product->boost->id,
                    'status' => $product->boost->status,
                    'start_date' => $product->boost->start_date,
                    'end_date' => $product->boost->end_date,
                    'created_at' => $product->boost->created_at->format('d-m-Y H:i:s')
                ] : null,
                'delivery_options' => $product->deliveryOptions->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'state' => $option->state,
                        'local_government' => $option->local_government,
                        'variant' => $option->variant,
                        'price' => $option->price,
                        'formatted_price' => 'N' . number_format($option->price, 0),
                        'is_free' => $option->is_free
                    ];
                }),
                'stats' => [
                    'views' => $product->views,
                    'impressions' => $product->impressions,
                    'clicks' => $product->clicks,
                    'carts' => $product->carts,
                    'orders' => $product->orders,
                    'chats' => $product->chats
                ],
                'reviews' => $product->reviews->take(5)->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'user' => [
                            'id' => $review->user->id,
                            'name' => $review->user->full_name,
                            'profile_picture' => $review->user->profile_picture ? asset('storage/' . $review->user->profile_picture) : null
                        ],
                        'created_at' => $review->created_at->format('d-m-Y H:i:s')
                    ];
                }),
                'reviews_count' => $product->reviews->count()
            ];

            return ResponseHelper::success($productDetails, 'Product details retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get product statistics for seller
     */
    public function getProductStatistics($userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $totalProducts = Product::where('store_id', $store->id)->count();
            $activeProducts = Product::where('store_id', $store->id)->where('status', 'active')->count();
            $draftProducts = Product::where('store_id', $store->id)->where('status', 'draft')->count();
            $inactiveProducts = Product::where('store_id', $store->id)->where('status', 'inactive')->count();
            $soldProducts = Product::where('store_id', $store->id)->where('is_sold', true)->count();
            $unavailableProducts = Product::where('store_id', $store->id)->where('is_unavailable', true)->count();

            $generalProducts = Product::where('store_id', $store->id)
                ->whereDoesntHave('boost')
                ->count();
            $sponsoredProducts = Product::where('store_id', $store->id)
                ->whereHas('boost', function ($q) {
                    $q->where('status', 'active');
                })
                ->count();

            $totalViews = ProductStat::whereHas('product', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->where('event_type', 'view')->count();

            $totalOrders = ProductStat::whereHas('product', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->where('event_type', 'order')->count();

            $totalRevenue = Product::where('store_id', $store->id)
                ->whereHas('orderItems')
                ->withSum('orderItems', 'line_total')
                ->get()
                ->sum('order_items_sum_line_total');

            return ResponseHelper::success([
                'product_counts' => [
                    'total' => $totalProducts,
                    'active' => $activeProducts,
                    'draft' => $draftProducts,
                    'inactive' => $inactiveProducts,
                    'sold' => $soldProducts,
                    'unavailable' => $unavailableProducts
                ],
                'product_types' => [
                    'general' => $generalProducts,
                    'sponsored' => $sponsoredProducts
                ],
                'engagement_stats' => [
                    'total_views' => $totalViews,
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'formatted_revenue' => 'N' . number_format($totalRevenue, 0)
                ],
                'conversion_rate' => $totalViews > 0 ? round(($totalOrders / $totalViews) * 100, 2) : 0
            ], 'Product statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update product status
     */
    public function updateProductStatus(Request $request, $userId, $productId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:draft,active,inactive',
                'is_sold' => 'boolean',
                'is_unavailable' => 'boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);
            
            $updateData = ['status' => $request->status];
            
            if ($request->has('is_sold')) {
                $updateData['is_sold'] = $request->is_sold;
            }
            
            if ($request->has('is_unavailable')) {
                $updateData['is_unavailable'] = $request->is_unavailable;
            }
            
            $product->update($updateData);

            return ResponseHelper::success([
                'product_id' => $product->id,
                'status' => $product->status,
                'is_sold' => $product->is_sold,
                'is_unavailable' => $product->is_unavailable,
                'updated_at' => $product->updated_at->format('d-m-Y H:i:s')
            ], 'Product status updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete a product
     */
    public function deleteProduct($userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);

            // Delete associated images
            foreach ($product->images as $image) {
                if (Storage::disk('public')->exists($image->path)) {
                    Storage::disk('public')->delete($image->path);
                }
                $image->delete();
            }

            // Delete product video
            if ($product->video && Storage::disk('public')->exists($product->video)) {
                Storage::disk('public')->delete($product->video);
            }

            // Delete associated variants and their images
            foreach ($product->variants as $variant) {
                foreach ($variant->images as $image) {
                    if (Storage::disk('public')->exists($image->path)) {
                        Storage::disk('public')->delete($image->path);
                    }
                    $image->delete();
                }
                $variant->delete();
            }

            // Delete product stats
            $product->productStats()->delete();

            // Delete boost if exists
            if ($product->boost) {
                $product->boost->delete();
            }

            $product->delete();

            return ResponseHelper::success(null, 'Product deleted successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get product analytics
     */
    public function getProductAnalytics(Request $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            $products = Product::where('store_id', $store->id)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->withCount([
                    'productStats as views' => fn($q) => $q->where('event_type', 'view'),
                    'productStats as orders' => fn($q) => $q->where('event_type', 'order'),
                ])
                ->get();

            $analytics = [
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'total_products' => $products->count(),
                'total_views' => $products->sum('views'),
                'total_orders' => $products->sum('orders'),
                'average_views_per_product' => $products->count() > 0 ? round($products->avg('views'), 2) : 0,
                'top_performing_products' => $products->sortByDesc('views')->take(5)->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'views' => $product->views,
                        'orders' => $product->orders,
                        'conversion_rate' => $product->views > 0 ? round(($product->orders / $product->views) * 100, 2) : 0
                    ];
                }),
                'products_by_status' => [
                    'active' => $products->where('status', 'active')->count(),
                    'draft' => $products->where('status', 'draft')->count(),
                    'inactive' => $products->where('status', 'inactive')->count()
                ]
            ];

            return ResponseHelper::success($analytics, 'Product analytics retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create a product for a seller (admin can create product for seller)
     */
    public function createProduct(ProductCreateUpdateRequest $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $data = $request->validated();
            $data['store_id'] = $store->id;
            
            return DB::transaction(function () use ($data) {
                // Create main product
                $product = Product::create($data);
                
                // Set initial quantity based on variants or default to 0
                $initialQuantity = 0;
                
                // Handle product video
                if (!empty($data['video']) && $data['video'] instanceof \Illuminate\Http\UploadedFile) {
                    $videoPath = $data['video']->store('products/videos', 'public');
                    $product->update(['video' => $videoPath]);
                }

                // Upload product-level images
                if (!empty($data['images'])) {
                    foreach ($data['images'] as $index => $file) {
                        $path = $file->store('products', 'public');
                        ProductImage::create([
                            'product_id' => $product->id,
                            'path' => $path,
                            'is_main' => $index === 0,
                        ]);
                    }
                }

                // Handle variants (if provided)
                if (!empty($data['variants'])) {
                    foreach ($data['variants'] as $variantData) {
                        // Create variant
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $variantData['sku'] ?? null,
                            'color' => $variantData['color'] ?? null,
                            'size' => $variantData['size'] ?? null,
                            'price' => $variantData['price'] ?? null,
                            'discount_price' => $variantData['discount_price'] ?? null,
                            'stock' => $variantData['stock'] ?? 0,
                        ]);
                        
                        // Add to total quantity
                        $initialQuantity += $variantData['stock'] ?? 0;

                        // Upload variant images
                        if (!empty($variantData['images'])) {
                            foreach ($variantData['images'] as $file) {
                                $path = $file->store('products', 'public');
                                ProductImage::create([
                                    'product_id' => $product->id,
                                    'variant_id' => $variant->id,
                                    'path' => $path,
                                ]);
                            }
                        }
                    }
                }
                
                // Update product quantity
                $product->update(['quantity' => $initialQuantity]);

                return ResponseHelper::success([
                    'product' => $product->load(['images', 'variants.images']),
                    'message' => 'Product created successfully for seller'
                ], 'Product created successfully for seller');
            });

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update a product (admin can edit seller's product)
     */
    public function updateProduct(ProductCreateUpdateRequest $request, $userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);
            $data = $request->validated();
            
            return DB::transaction(function () use ($product, $data) {
                // Update main product
                $product->update($data);

                // Handle product images
                if (!empty($data['images'])) {
                    foreach ($data['images'] as $file) {
                        $path = $file->store('products', 'public');
                        ProductImage::create([
                            'product_id' => $product->id,
                            'path' => $path,
                        ]);
                    }
                }

                // Handle variants
                if (!empty($data['variants'])) {
                    foreach ($data['variants'] as $variantData) {
                        if (!empty($variantData['id'])) {
                            // Update existing variant
                            $variant = ProductVariant::where('product_id', $product->id)
                                ->where('id', $variantData['id'])
                                ->first();
                            if ($variant) {
                                $variant->update($variantData);
                            }
                        } else {
                            // Create new variant
                            $variant = ProductVariant::create([
                                'product_id' => $product->id,
                                'sku' => $variantData['sku'] ?? null,
                                'color' => $variantData['color'] ?? null,
                                'size' => $variantData['size'] ?? null,
                                'price' => $variantData['price'] ?? null,
                                'discount_price' => $variantData['discount_price'] ?? null,
                                'stock' => $variantData['stock'] ?? 0,
                            ]);
                        }

                        // Handle variant images
                        if (!empty($variantData['images'])) {
                            foreach ($variantData['images'] as $file) {
                                $path = $file->store('products', 'public');
                                ProductImage::create([
                                    'product_id' => $product->id,
                                    'variant_id' => $variant->id,
                                    'path' => $path,
                                ]);
                            }
                        }
                    }
                }

                // Update product quantity from variants
                $product->updateQuantityFromVariants();

                return ResponseHelper::success([
                    'product' => $product->load(['images', 'variants.images']),
                    'message' => 'Product updated successfully'
                ], 'Product updated successfully');
            });

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Boost a product (admin can boost seller's product)
     */
    public function boostProduct(BoostProductRequest $request, $userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);
            
            // Check if product already has an active boost
            $existingBoost = BoostProduct::where('product_id', $productId)
                ->where('status', 'active')
                ->first();
                
            if ($existingBoost) {
                return ResponseHelper::error('Product already has an active boost', 400);
            }

            $data = $request->validated();
            
            // Calculate boost totals (simplified calculation)
            $dailyBudget = $data['budget'];
            $duration = $data['duration'];
            $subtotal = $dailyBudget * $duration;
            $platformFee = $subtotal * 0.1; // 10% platform fee
            $totalAmount = $subtotal + $platformFee;
            
            $boost = BoostProduct::create([
                'product_id' => $productId,
                'store_id' => $store->id,
                'location' => $data['location'] ?? null,
                'duration' => $duration,
                'daily_budget' => $dailyBudget,
                'budget' => $dailyBudget,
                'subtotal' => $subtotal,
                'platform_fee' => $platformFee,
                'total_amount' => $totalAmount,
                'start_date' => $data['start_date'] ?? now(),
                'end_date' => $data['start_date'] ? 
                    \Carbon\Carbon::parse($data['start_date'])->addDays($duration) : 
                    now()->addDays($duration),
                'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'wallet',
                'estimated_reach' => $dailyBudget * 100, // Mock calculation
                'estimated_clicks' => $dailyBudget * 10, // Mock calculation
                'estimated_cpc' => $dailyBudget / 10 // Mock calculation
            ]);
            
            return ResponseHelper::success([
                'boost' => $boost,
                'message' => 'Product boost created successfully'
            ], 'Product boost created successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed product stats
     */
    public function getProductStats($userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);

            // Get detailed stats
            $stats = ProductStat::where('product_id', $productId)
                ->selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray();

            $detailedStats = [
                'views' => $stats['view'] ?? 0,
                'impressions' => $stats['impression'] ?? 0,
                'visitors' => $stats['view'] ?? 0, // Assuming visitors = views for now
                'clicks' => $stats['click'] ?? 0,
                'carts' => $stats['add_to_cart'] ?? 0,
                'chats' => $stats['chat'] ?? 0,
                'reviews' => $product->reviews()->count(),
                'completed_orders' => $stats['order'] ?? 0
            ];

            // Get daily stats for the last 30 days
            $dailyStats = ProductStat::where('product_id', $productId)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, event_type, COUNT(*) as count')
                ->groupBy('date', 'event_type')
                ->orderBy('date')
                ->get()
                ->groupBy('date')
                ->map(function ($dayStats) {
                    return $dayStats->pluck('count', 'event_type')->toArray();
                });

            return ResponseHelper::success([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'stats' => $detailedStats,
                'daily_stats' => $dailyStats,
                'generated_at' => now()->format('d-m-Y H:i:s')
            ], 'Product stats retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update product quantity
     */
    public function updateProductQuantity(Request $request, $userId, $productId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:0'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);
            $product->update(['quantity' => $request->quantity]);
            
            return ResponseHelper::success([
                'product_id' => $product->id,
                'quantity' => $product->quantity,
                'updated_at' => $product->updated_at->format('d-m-Y H:i:s')
            ], 'Product quantity updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark product as sold
     */
    public function markProductAsSold($userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);
            $product->update([
                'is_sold' => true,
                'is_unavailable' => false
            ]);
            
            return ResponseHelper::success([
                'product_id' => $product->id,
                'is_sold' => $product->is_sold,
                'is_unavailable' => $product->is_unavailable,
                'updated_at' => $product->updated_at->format('d-m-Y H:i:s')
            ], 'Product marked as sold successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark product as unavailable
     */
    public function markProductAsUnavailable($userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);
            $product->update([
                'is_unavailable' => true,
                'is_sold' => false
            ]);
            
            return ResponseHelper::success([
                'product_id' => $product->id,
                'is_sold' => $product->is_sold,
                'is_unavailable' => $product->is_unavailable,
                'updated_at' => $product->updated_at->format('d-m-Y H:i:s')
            ], 'Product marked as unavailable successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark product as available
     */
    public function markProductAsAvailable($userId, $productId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $product = Product::where('store_id', $store->id)->findOrFail($productId);
            $product->update([
                'is_sold' => false,
                'is_unavailable' => false
            ]);
            
            return ResponseHelper::success([
                'product_id' => $product->id,
                'is_sold' => $product->is_sold,
                'is_unavailable' => $product->is_unavailable,
                'updated_at' => $product->updated_at->format('d-m-Y H:i:s')
            ], 'Product marked as available successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
