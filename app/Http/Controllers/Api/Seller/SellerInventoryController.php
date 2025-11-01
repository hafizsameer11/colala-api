<?php

namespace App\Http\Controllers\Api\Seller;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SellerInventoryController extends Controller
{
    /**
     * Get detailed inventory for seller's products
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInventory(Request $request)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Build query with relationships
            $query = Product::where('store_id', $store->id)
                ->with([
                    'category:id,title',
                    'images:id,product_id,url,is_main',
                    'variants:id,product_id,sku,color,size,price,discount_price,stock',
                    'orderItems' => function ($q) {
                        $q->select('id', 'product_id', 'variant_id', 'qty', 'line_total')
                          ->with('storeOrder:id,status,created_at');
                    }
                ])
                ->withCount([
                    'orderItems as total_sold' => function ($q) {
                        $q->select(DB::raw('COALESCE(SUM(qty), 0)'));
                    },
                    'orderItems as total_revenue' => function ($q) {
                        $q->select(DB::raw('COALESCE(SUM(line_total), 0)'));
                    },
                    'productStats as views' => function ($q) {
                        $q->where('event_type', 'view');
                    },
                    'productStats as impressions' => function ($q) {
                        $q->where('event_type', 'impression');
                    },
                    'productStats as orders' => function ($q) {
                        $q->where('event_type', 'order');
                    },
                ]);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('category_id') && $request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('low_stock') && $request->low_stock) {
                $lowStockThreshold = $request->get('low_stock_threshold', 10);
                $query->where(function ($q) use ($lowStockThreshold) {
                    $q->where('quantity', '<=', $lowStockThreshold)
                      ->orWhereHas('variants', function ($variantQuery) use ($lowStockThreshold) {
                          $variantQuery->where('stock', '<=', $lowStockThreshold);
                      });
                });
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->has('out_of_stock') && $request->out_of_stock) {
                $query->where(function ($q) {
                    $q->where('quantity', '<=', 0)
                      ->orWhere(function ($subQ) {
                          $subQ->where('has_variants', true)
                               ->whereDoesntHave('variants', function ($variantQ) {
                                   $variantQ->where('stock', '>', 0);
                               });
                      });
                });
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $perPage = $request->get('per_page', 20);
            $products = $query->paginate($perPage);

            // Transform data
            $products->getCollection()->transform(function ($product) {
                // Calculate total stock (including variants)
                $totalStock = $product->has_variants 
                    ? $product->variants->sum('stock')
                    : $product->quantity;

                // Calculate available stock
                $availableStock = $product->has_variants
                    ? $product->variants->sum(function ($variant) {
                        return max(0, $variant->stock);
                    })
                    : max(0, $product->quantity);

                // Check if low stock (threshold: 10)
                $lowStockThreshold = 10;
                $isLowStock = $availableStock <= $lowStockThreshold && $availableStock > 0;
                $isOutOfStock = $availableStock <= 0;

                // Get variant details with stock info
                $variants = $product->variants->map(function ($variant) use ($lowStockThreshold) {
                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'color' => $variant->color,
                        'size' => $variant->size,
                        'price' => $variant->price,
                        'discount_price' => $variant->discount_price,
                        'stock' => $variant->stock,
                        'is_low_stock' => $variant->stock <= $lowStockThreshold && $variant->stock > 0,
                        'is_out_of_stock' => $variant->stock <= 0,
                    ];
                });

                // Get recent sales (last 30 days)
                $recentSales = $product->orderItems()
                    ->whereHas('storeOrder', function ($q) {
                        $q->where('created_at', '>=', now()->subDays(30));
                    })
                    ->sum('qty');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->title,
                    ] : null,
                    'price' => $product->price,
                    'discount_price' => $product->discount_price,
                    'has_variants' => $product->has_variants,
                    'status' => $product->status,
                    'is_sold' => $product->is_sold,
                    'is_unavailable' => $product->is_unavailable,
                    
                    // Inventory details
                    'stock' => [
                        'total' => $totalStock,
                        'available' => $availableStock,
                        'product_quantity' => $product->quantity,
                        'is_low_stock' => $isLowStock,
                        'is_out_of_stock' => $isOutOfStock,
                        'low_stock_threshold' => $lowStockThreshold,
                    ],
                    
                    // Variants
                    'variants' => $variants->toArray(),
                    'variants_count' => $variants->count(),
                    
                    // Images
                    'images' => $product->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'url' => asset('storage/' . $image->url),
                            'is_main' => $image->is_main,
                        ];
                    }),
                    'main_image' => $product->images->where('is_main', true)->first()
                        ? asset('storage/' . $product->images->where('is_main', true)->first()->url)
                        : ($product->images->first() 
                            ? asset('storage/' . $product->images->first()->url)
                            : null),
                    
                    // Sales statistics
                    'statistics' => [
                        'total_sold' => (int) ($product->total_sold ?? 0),
                        'total_revenue' => (float) ($product->total_revenue ?? 0),
                        'recent_sales_30days' => (int) $recentSales,
                        'views' => (int) ($product->views ?? 0),
                        'impressions' => (int) ($product->impressions ?? 0),
                        'orders' => (int) ($product->orders ?? 0),
                    ],
                    
                    // Timestamps
                    'created_at' => $product->created_at ? $product->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $product->updated_at ? $product->updated_at->format('Y-m-d H:i:s') : null,
                ];
            });

            // Get summary statistics
            $summary = [
                'total_products' => Product::where('store_id', $store->id)->count(),
                'active_products' => Product::where('store_id', $store->id)->where('status', 'active')->count(),
                'inactive_products' => Product::where('store_id', $store->id)->where('status', 'inactive')->count(),
                'low_stock_products' => Product::where('store_id', $store->id)
                    ->where(function ($q) {
                        $q->where('quantity', '<=', 10)
                          ->orWhereHas('variants', function ($variantQ) {
                              $variantQ->where('stock', '<=', 10);
                          });
                    })
                    ->where('quantity', '>', 0)
                    ->count(),
                'out_of_stock_products' => Product::where('store_id', $store->id)
                    ->where(function ($q) {
                        $q->where('quantity', '<=', 0)
                          ->orWhere(function ($subQ) {
                              $subQ->where('has_variants', true)
                                   ->whereDoesntHave('variants', function ($variantQ) {
                                       $variantQ->where('stock', '>', 0);
                                   });
                          });
                    })
                    ->count(),
                'products_with_variants' => Product::where('store_id', $store->id)
                    ->where('has_variants', true)
                    ->count(),
                'total_value' => Product::where('store_id', $store->id)
                    ->with('variants:id,product_id,stock,price')
                    ->get()
                    ->sum(function ($product) {
                        if ($product->has_variants) {
                            return $product->variants->sum(function ($variant) {
                                return $variant->stock * $variant->price;
                            });
                        }
                        return $product->quantity * $product->price;
                    }),
            ];

            return ResponseHelper::success([
                'summary' => $summary,
                'products' => $products,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ], 'Inventory retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to get inventory: ' . $e->getMessage());
            return ResponseHelper::error('Failed to retrieve inventory', 500);
        }
    }
}
