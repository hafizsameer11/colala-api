<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    //
     protected $fillable = ['cart_id','store_id','product_id','variant_id','qty','unit_price','unit_discount_price'];
    public function cart(){ return $this->belongsTo(Cart::class); }
    public function store(){ return $this->belongsTo(Store::class); }
    public function product(){ return $this->belongsTo(Product::class); }
    public function variant(){ return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
