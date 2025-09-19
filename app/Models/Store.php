<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    //
     protected $fillable = [
        'user_id','store_name','store_email','store_phone','store_location',
        'profile_image','banner_image','category_id','theme_color',
        'referral_code','status'
    ];

    public function socialLinks() { return $this->hasMany(StoreSocialLink::class); }
    public function businessDetails() { return $this->hasOne(StoreBusinessDetail::class); }
    public function addresses() { return $this->hasMany(StoreAddress::class); }
    public function deliveryPricing() { return $this->hasMany(StoreDeliveryPricing::class); }
    public function user() { return $this->belongsTo(User::class); }
}
