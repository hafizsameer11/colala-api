<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBulkPrice extends Model
{
     protected $fillable = ['product_id','min_quantity','amount','discount_percent'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
