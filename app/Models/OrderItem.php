<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    //
     protected $fillable = ['store_order_id','product_id','variant_id','name','sku','color','size','unit_price','unit_discount_price','qty','line_total'];
    public function storeOrder(){ return $this->belongsTo(StoreOrder::class); }
    public function product(){ return $this->belongsTo(Product::class); }
    public function variant(){ return $this->belongsTo(ProductVariant::class,'variant_id'); }
}
