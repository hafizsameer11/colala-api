<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreDeliveryPricing extends Model
    {
        protected $table = 'store_delivery_pricing';
     protected $fillable = ['store_id','state','local_government','variant','price','is_free'];
    public function store() { return $this->belongsTo(Store::class); }
    public function products()
{
    return $this->belongsToMany(Product::class);
}


}
