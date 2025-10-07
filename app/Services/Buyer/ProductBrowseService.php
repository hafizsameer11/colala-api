<?php 


namespace App\Services\Buyer;

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

        // ✅ New arrivals (created this month)
        $newArrivals = Product::where('category_id', $categoryId)
            ->where('status', 'active')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->with(['images', 'store'])
            ->latest()
            ->take(10)
            ->get();

        // ✅ Trending products (latest 4)
        $trendingProducts = Product::where('category_id', $categoryId)
            ->where('status', 'active')
            ->with(['images', 'store'])
            ->latest()
            ->take(4)
            ->get();

        return [
            'all_products'      => $allProducts,
            'new_arrivals'      => $newArrivals,
            'trending_products' => $trendingProducts,
        ];
    }

    public function topSelling() {
        return Product::with(['images','store'])
            ->take(20)->latest()
            ->get();
    }
}
