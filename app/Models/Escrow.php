<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Escrow extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'store_order_id',
        'order_item_id',
        'amount',
        'status',
        'shipping_fee'
    ];

    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }
    
    public function order() 
    { 
        return $this->belongsTo(Order::class); 
    }
    
    public function storeOrder() 
    { 
        return $this->belongsTo(StoreOrder::class); 
    }
    
    public function orderItem() 
    { 
        return $this->belongsTo(OrderItem::class); 
    }
}
