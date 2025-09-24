<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'store_id','name','category_id','brand','description',
        'price','discount_price','has_variants','video','status',
        'coupon_code','discount','loyality_points_applicable'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
public function deliveryOptions()
{
    return $this->belongsToMany(StoreDeliveryPricing::class, 'product_delivery_pricing', 'product_id', 'delivery_pricing_id');
}

}
