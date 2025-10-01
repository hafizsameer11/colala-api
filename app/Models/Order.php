<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    //
     protected $fillable = ['order_no','user_id','delivery_address_id','payment_method','payment_status','items_total','shipping_total','platform_fee','discount_total','grand_total','meta'];
    protected $casts = ['meta' => 'array'];
    public function user(){ return $this->belongsTo(User::class); }
    public function storeOrders(){ return $this->hasMany(StoreOrder::class); }
    public function orderTracking(){ return $this->hasMany(OrderTracking::class); }
    public function items(){
        return $this->hasMany(OrderItem::class);
    }
}
