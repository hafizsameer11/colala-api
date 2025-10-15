<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
     protected $fillable = [
         'product_id',
         'variant_id',
         'path',
         'is_main',
         'gcs_uri',
         'vision_reference_image_name',
         'vision_index_status',
         'vision_last_error'
     ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
