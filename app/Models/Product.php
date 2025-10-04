<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'store_id',
        'name',
        'category_id',
        'brand',
        'description',
        'price',
        'discount_price',
        'has_variants',
        'video',
        'status',
        'coupon_code',
        'discount',
        'loyality_points_applicable'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    public function deliveryOptions()
    {
        return $this->belongsToMany(StoreDeliveryPricing::class, 'product_delivery_pricing', 'product_id', 'delivery_pricing_id');
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function variations()
    {
        return $this->hasMany(ProductVariant::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function services()
    {
        return $this->hasMany(Service::class);
    }
    public function productStats()
    {
        return $this->hasMany(ProductStat::class);
    }
    public function statsSummary(): array
{
    return $this->productStats()
        ->selectRaw('event_type, COUNT(*) as total')
        ->groupBy('event_type')
        ->pluck('total','event_type')
        ->toArray();
}
public function reviews() // ✅ correct spelling
{
    // Product -> OrderItem(product_id) -> ProductReview(order_item_id)
    return $this->hasManyThrough(
        ProductReview::class,   // final
        OrderItem::class,       // through
        'product_id',           // FK on order_items -> products.id
        'order_item_id',        // FK on product_reviews -> order_items.id
        'id',                   // local key on products
        'id'                    // local key on order_items
    );
}
public function boost()
{
    return $this->hasOne(BoostProduct::class, 'product_id');
}
public function isBoosted(): bool
{
    $boost = $this->boost()
        ->where('status', 'active')
     
        ->exists();

    return $boost;
}

//now send the count of every type of product stat
    
}
