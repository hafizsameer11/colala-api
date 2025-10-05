<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreDeliveryPricing;
use App\Services\Buyer\ProductBrowseService;

class ProductBrowseController extends Controller
{
  public function __construct(private ProductBrowseService $svc) {}
  public function byCategory($categoryId)
  {
    try {
      return ResponseHelper::success($this->svc->byCategory((int)$categoryId));
    } catch (\Exception $e) {
      return ResponseHelper::error($e->getMessage(), 500);
    }
  }
  public function topSelling()
  {
    try {
      return ResponseHelper::success($this->svc->topSelling());
    } catch (\Exception $e) {
      return ResponseHelper::error($e->getMessage(), 500);
    }
  }
  public function storeDeliveryAddresses($storeId)
  {
    try {
      $deliverAddresses = StoreDeliveryPricing::where('store_id', $storeId)->get();
      if ($deliverAddresses->isEmpty()) {
        return ResponseHelper::error('No delivery addresses found for this store', 404);
      }
      return ResponseHelper::success($deliverAddresses);
    } catch (\Exception $e) {
      return ResponseHelper::error($e->getMessage(), 500);
    }
  }
public function productDetails($productId)
{
    try {
        $product = Product::with([
            'store' => function ($q) {
                $q->withCount('followers')
                  ->withSum('soldItems', 'qty');
            },
            'store.soldItems',
            'store.socialLinks',
            'category',
            'images',
            'variations',
            'reviews',
            'boost', // include relation
        ])->find($productId);

        if (!$product) {
            return ResponseHelper::error('Product not found', 404);
        }

        $data = $product->toArray();
        //get the quantity of product from variants which have stock
        $data['qty'] = $product->variations->where('stock', '>', 0)->sum('stock');
        $data['is_boosted'] = $product->isBoosted(); // ✅ boolean value

        return ResponseHelper::success($data);
    } catch (\Exception $e) {
        return ResponseHelper::error($e->getMessage(), 500);
    }
}



}
