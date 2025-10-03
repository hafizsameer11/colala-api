<?php 

// app/Helpers/ProductStatHelper.php
namespace App\Helpers;

use App\Models\ProductStat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ProductStatHelper
{
    /**
     * Record an event for a product.
     *
     * @param int $productId
     * @param string $eventType (view, impression, click, add_to_cart, order, chat)
     * @return ProductStat
     */
    public static function record(int $productId, string $eventType): ProductStat
    {
        return ProductStat::create([
            'product_id' => $productId,
            'event_type' => $eventType,
            'user_id'    => Auth::id(),
            'ip'         => Request::ip(),
        ]);
    }
}
