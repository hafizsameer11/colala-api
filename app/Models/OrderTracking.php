<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'notes',
        'delivery_code',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
