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
        $products = Product::with(['images', 'store', 'boost'])
            ->join('boost_products', 'products.id', '=', 'boost_products.product_id')
            ->whereIn('boost_products.status', ['running', 'scheduled'])
            ->where('boost_products.payment_status', 'paid')
            ->where('products.status', 'active')
            ->where('products.is_unavailable', false)
            ->orderByDesc('boost_products.start_date')
            ->orderByDesc('boost_products.created_at')
            ->select('products.*')
            ->distinct()
            ->take(20)
            ->get();

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
