<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStat extends Model
{
      protected $fillable = [
        'product_id','event_type','user_id','ip'
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
