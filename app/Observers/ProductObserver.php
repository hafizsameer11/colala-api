<?php

namespace App\Observers;

use App\Models\Product;
use App\Jobs\Vision\IndexProductToVision;

class ProductObserver
{
    public function created(Product $product): void
    {
        dispatch(new IndexProductToVision($product->id))->onQueue('vision');
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged(['name', 'brand', 'category_id'])) {
            dispatch(new IndexProductToVision($product->id))->onQueue('vision');
        }
    }
}
