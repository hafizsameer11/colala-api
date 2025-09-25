<?php 


namespace App\Services\Buyer;

use App\Models\Category;
use App\Models\Product;

class ProductBrowseService {
    public function byCategory(int $categoryId) {
       return Product::where('category_id',$categoryId)->with(['images','store'])->where('status','active')->paginate(20);
        // $category = Category::with('children:id,parent_id')->findOrFail($categoryId);

        // $ids = collect([$category->id])
    }
}
