<?php 


namespace App\Services\Buyer;

use App\Helpers\ProductStatHelper;
use App\Models\Category;
use App\Models\Product;
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
        $products = Product::with(['images','store'])
            ->take(20)->latest()
            ->get();

        // Record impression for top selling products
        foreach ($products as $product) {
            ProductStatHelper::record($product->id, 'impression');
        }

        return $products;
    }
}
