<?php

namespace App\Services;

use App\Models\ProductBulkPrice;
use App\Models\Product;

class ProductBulkPriceService
{
    public function store($productId, array $prices)
    {
        $product = Product::findOrFail($productId);

        foreach ($prices as $price) {
            ProductBulkPrice::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'min_quantity' => $price['min_quantity'],
                ],
                [
                    'amount' => $price['amount'],
                    'discount_percent' => $price['discount_percent'] ?? null,
                ]
            );
        }
    }
}
