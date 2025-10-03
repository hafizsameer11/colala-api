<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrder extends Model
{
     protected $fillable = ['order_id','store_id','status','delivery_pricing_id','shipping_fee','items_subtotal','discount','subtotal_with_shipping'];
    public function order(){ return $this->belongsTo(Order::class); }
    public function store(){ return $this->belongsTo(Store::class); }
    public function items(){ return $this->hasMany(OrderItem::class); }
    public function deliveryPricing(){ return $this->belongsTo(StoreDeliveryPricing::class,'delivery_pricing_id'); }
    public function orderTracking(){ return $this->hasMany(OrderTracking::class); }
}
