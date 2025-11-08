<?php 


namespace App\Services\Buyer;

use App\Helpers\ProductStatHelper;
use App\Helpers\BoostMetricsHelper;
use App\Models\Category;
use App\Models\Product;
use App\Models\BoostProduct;
use Carbon\Carbon;

class ProductBrowseService {
 public function byCategory(int $categoryId): array
    {
        // ✅ All products in the category
        $allProducts = Product::where('category_id', $categoryId)
            ->where('status', 'active')
            ->with(['images', 'store'])
            ->latest()
            ->paginate(20);

        // Record impression for each product in paginated results
        foreach ($allProducts->items() as $product) {
            ProductStatHelper::record($product->id, 'impression');
        }

        // ✅ New arrivals (created this month)
        $newArrivals = Product::where('category_id', $categoryId)
            ->where('status', 'active')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->with(['images', 'store'])
            ->latest()
            ->take(10)
            ->get();

        // Record impression for new arrivals
        foreach ($newArrivals as $product) {
            ProductStatHelper::record($product->id, 'impression');
        }

        // ✅ Trending products (latest 4)
        $trendingProducts = Product::where('category_id', $categoryId)
            ->where('status', 'active')
            ->with(['images', 'store'])
            ->latest()
            ->take(4)
            ->get();

        // Record impression for trending products
        foreach ($trendingProducts as $product) {
            ProductStatHelper::record($product->id, 'impression');
        }

        return [
            'all_products'      => $allProducts,
            'new_arrivals'      => $newArrivals,
            'trending_products' => $trendingProducts,
        ];
    }

    public function topSelling() {
        // Get products that have active boosts (running or scheduled, and paid)
        // Use subquery to get the latest boost per product, then join
        $products = Product::with(['images', 'store', 'boost'])
            ->whereIn('id', function ($query) {
                $query->select('product_id')
                    ->from('boost_products')
                    ->whereIn('status', ['running', 'scheduled'])
                    ->where('payment_status', 'paid')
                    ->whereIn('id', function ($subQuery) {
                        // Get the latest boost for each product
                        $subQuery->selectRaw('MAX(id)')
                            ->from('boost_products')
                            ->whereIn('status', ['running', 'scheduled'])
                            ->where('payment_status', 'paid')
                            ->groupBy('product_id');
                    });
            })
            ->where('status', 'active')
            ->where('is_unavailable', false)
            ->whereHas('boost', function ($query) {
                $query->whereIn('status', ['running', 'scheduled'])
                      ->where('payment_status', 'paid');
            })
            ->with(['boost' => function ($query) {
                $query->whereIn('status', ['running', 'scheduled'])
                      ->where('payment_status', 'paid')
                      ->latest('start_date')
                      ->latest('created_at');
            }])
            ->get()
            ->sortByDesc(function ($product) {
                $boost = $product->boost;
                if (!$boost) return 0;
                return $boost->start_date ? $boost->start_date->timestamp : $boost->created_at->timestamp;
            })
            ->take(20)
            ->values();

        // Record impression for top selling products and update boost records
        foreach ($products as $product) {
            // Record impression in product stats
            ProductStatHelper::record($product->id, 'impression');
            
            // Update boost metrics (impressions, reach, amount_spent, CPC)
            BoostMetricsHelper::recordImpression($product->id);
        }

        return $products;
    }
}
