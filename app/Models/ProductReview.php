<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    //
    protected $fillable = ['order_item_id','user_id','rating','comment','images'];
    protected $casts = ['images'=>'array'];
    public function orderItem(){ return $this->belongsTo(OrderItem::class); }
    public function user(){ return $this->belongsTo(User::class); }
}
