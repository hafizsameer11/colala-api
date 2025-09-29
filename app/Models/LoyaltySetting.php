<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    //
    protected $fillable = [
        'store_id','points_per_order','points_per_referral',
        'enable_order_points','enable_referral_points'
    ];

    public function store() { return $this->belongsTo(Store::class); }
}
