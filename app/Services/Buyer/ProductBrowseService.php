<?php 


namespace App\Services\Buyer;

use App\Models\Category;
use App\Models\Product;

class ProductBrowseService {
    public function byCategory(int $categoryId) {
        $category = Category::with('children:id,parent_id')->findOrFail($categoryId);

        $ids = collect([$category->id])
            ->merge($category->children->pluck('id'))
            ->unique()->values();

        return Product::with(['images','store'])
            ->whereIn('category_id', $ids)
            ->where('status','active')
            ->paginate(20);
    }
}
