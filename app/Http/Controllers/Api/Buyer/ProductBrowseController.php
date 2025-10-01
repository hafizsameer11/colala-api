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
        $products = Product::with([
            'store' => function ($q) {
                $q->withCount('followers')       // followers_count
                  ->withSum('soldItems', 'qty'); // sold_items_sum_qty
            },
            'store.soldItems',
          'store.socialLinks',
            'category',
            'images',
            'variations'
        ])->find($productId);

        if (!$products) {
            return ResponseHelper::error('Product not found', 404);
        }

        return ResponseHelper::success($products);
    } catch (\Exception $e) {
        return ResponseHelper::error($e->getMessage(), 500);
    }
}


}
