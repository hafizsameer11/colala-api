<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentHasManyDeep\HasRelationships; // ✅ add this


class Store extends Model
{
    //
    use SoftDeletes;

    use HasRelationships; // ✅ very important
    protected $fillable = [
        'user_id',
        'store_name',
        'store_email',
        'store_phone',
        'is_phone_visible',
        'store_location',
        'profile_image',
        'banner_image',
        'category_id',
        'theme_color',
        'referral_code',
        'status',
        'visibility'
    ];
    protected $appends = [
        'average_rating',
    ];

    protected static function booted()
{
    static::addGlobalScope('visible', function ($query) {
        $query->where('visibility', 1);
    });
}

    public function socialLinks()
    {
        return $this->hasMany(StoreSocialLink::class);
    }
    public function businessDetails()
    {
        return $this->hasOne(StoreBusinessDetail::class);
    }
    public function addresses()
    {
        return $this->hasMany(StoreAddress::class);
    }
    public function deliveryPricing()
    {
        return $this->hasMany(StoreDeliveryPricing::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all users associated with this store
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'store_categories', 'store_id', 'category_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function services()
    {
        return $this->hasMany(Service::class);
    }
    public function orders()
    {
        return $this->hasMany(StoreOrder::class);
    }
    // public function reviews() { return $this->hasMany(StoreReview::class); }
    // public function posts() { return $this->hasMany(StorePost::class); }
    public function chats()
    {
        return $this->hasMany(Chat::class);
    }
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

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
    public function productReviews()
    {
        return $this->hasManyDeep(
            ProductReview::class,
            [Product::class, OrderItem::class], // through tables
            [
                'store_id',      // FK on products table
                'product_id',    // FK on order_items table
                'order_item_id', // FK on product_reviews table
            ],
            [
                'id',            // stores.id
                'id',            // products.id
                'id',            // order_items.id
            ]
        );
    }

    public function getTotalSoldAttribute(): int
    {
        // sum of qty column = total number of items sold
        return $this->soldItems()->sum('qty');
    }
    public function followers()
    {
        return $this->hasMany(StoreFollow::class);
    }
    public function storeReveiews()
    {
        return $this->hasMany(StoreReview::class);
    }
    public function followersCount()
    {
        return $this->followers()->count();
    }
    // app/Models/Store.php

public function getFollowersCountAttribute(): int
{
    return $this->followers()->count();
}


public function getAverageRatingAttribute(): float
{
    $average = round($this->storeReveiews()->avg('rating') ?? 0, 1);
    if ($average <= 0) {
        // generate a random rating between 4.0 and 5.0 inclusive, one decimal place
        // return round(mt_rand(40, 50) / 10, 1);
        return 0;
    }
    
    return $average;
}

public function banners(){
    return $this->hasMany(Banner::class);
}
public function announcements(){
    return $this->hasMany(Announcement::class);
}
    // public function storeAddress(){
    //     return $this->hasMany(StoreAddress::class);
    // }
}
