<?php

namespace App\Services;

use App\Models\Product;

class ProductDeliveryOptionService
{
    public function attach($productId, array $deliveryOptionIds)
    {
        $product = Product::findOrFail($productId);
        $product->deliveryOptions()->sync($deliveryOptionIds);
    }
}
