<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    protected $fillable = [
        'store_order_id',
        'status',
        'notes',
        'delivery_code',
    ];

    public function storeOrder()
    {
        return $this->belongsTo(StoreOrder::class);
    }
}
