<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Jobs\Vision\IndexProductImageToVision;

class ProductImageObserver
{
    public function created(ProductImage $image): void
    {
        dispatch(new IndexProductImageToVision($image->id))->onQueue('vision');
    }

    public function updated(ProductImage $image): void
    {
        if ($image->wasChanged(['path'])) {
            dispatch(new IndexProductImageToVision($image->id))->onQueue('vision');
        }
    }
}
