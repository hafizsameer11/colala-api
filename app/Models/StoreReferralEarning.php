<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreReferralEarning extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'amount', 'order_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}


