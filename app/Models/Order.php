<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    //
    use SoftDeletes;

     protected $fillable = ['order_no','user_id','delivery_address_id','payment_method','payment_status','items_total','shipping_total','platform_fee','discount_total','grand_total','meta'];
    protected $casts = ['meta' => 'array'];
    public function user(){ return $this->belongsTo(User::class); }
    public function storeOrders(){ return $this->hasMany(StoreOrder::class); }
    public function orderTracking(){ return $this->hasMany(OrderTracking::class); }
    public function escrows(){ return $this->hasMany(Escrow::class); }
    public function deliveryAddress(){
        return $this->belongsTo(UserAddress::class); }
    // public function items(){
    //     return $this->hasMany(OrderItem::class);
    // }
}
