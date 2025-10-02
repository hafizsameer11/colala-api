<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    //
     protected $fillable = [
        'store_id','code','discount_type','discount_value',
        'max_usage','usage_per_user','times_used','expiry_date','status'
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function store() {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive($q) {
        return $q->where('status','active')
                 ->where(function($query){
                     $query->whereNull('expiry_date')
                           ->orWhere('expiry_date','>=', now());
                 });
    }
}
