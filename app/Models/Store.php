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
    public function categories()
{
    return $this->belongsToMany(Category::class, 'store_categories', 'store_id', 'category_id');
}
public function products() { return $this->hasMany(Product::class); }
    public function services() { return $this->hasMany(Service::class); }
    public function orders() { return $this->hasMany(StoreOrder::class); }
    // public function reviews() { return $this->hasMany(StoreReview::class); }
    // public function posts() { return $this->hasMany(StorePost::class); }
    public function chats() { return $this->hasMany(Chat::class); }
    public function supportTickets() { return $this->hasMany(SupportTicket::class); }

    public function soldItems()
{
    return $this->hasManyThrough(
        OrderItem::class,
        Product::class,
        'store_id',
        'product_id',
        'id',
        'id'
    );
}

public function getTotalSoldAttribute(): int
{
    // sum of qty column = total number of items sold
    return $this->soldItems()->sum('qty');
}

}
