<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

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
        'loyality_points_applicable',
        'is_sold',
        'is_unavailable',
        'quantity',
        'referral_fee',
        'referral_person_limit',
        'vision_product_name',
        'vision_product_set',
        'vision_index_status',
        'vision_indexed_at',
        'vision_last_error'
    ];
    protected $appends = [
        'average_rating',
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
public function getAverageRatingAttribute(): float
{
    $average = $this->reviews()->avg('rating');
    
    // If there are no reviews, return 0
    return $average !== null ? (float) $average : 0.0;
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

    /**
     * Update product quantity based on variant stock totals
     */
    public function updateQuantityFromVariants()
    {
        $totalStock = $this->variants()->sum('stock');
        $this->update(['quantity' => $totalStock]);
        return $this;
    }
    public function getDeliveryFee(?int $addressId = null): float
    {
        // ✅ Load all delivery options for this product
        $query = $this->deliveryOptions();
    
        // 🧠 Default delivery pricing (if nothing matches)
        $defaultDelivery = $query->first();
        if (!$defaultDelivery) {
            return 0;
        }
    
        // ✅ Check if this product has free delivery
        if ($defaultDelivery->is_free) {
            return 0;
        }
    
        // ✅ Try to match the delivery price with user's address
        if ($addressId) {
            $address = \App\Models\UserAddress::find($addressId);
    
            if ($address) {
                // Try exact match first
                $matched = $this->deliveryOptions()
                    ->where(function ($q) use ($address) {
                        $q->where('state', $address->state)
                          ->where('local_government', $address->city ?? $address->local_government);
                    })
                    ->first();
    
                if ($matched) {
                    return $matched->is_free ? 0 : (float) $matched->price;
                }
    
                // Try partial matches for flexibility (LIKE)
                $matchedLike = $this->deliveryOptions()
                    ->where(function ($q) use ($address) {
                        $q->where('state', 'LIKE', "%{$address->state}%")
                          ->orWhere('local_government', 'LIKE', "%{$address->city}%")
                          ->orWhere('local_government', 'LIKE', "%{$address->local_government}%");
                    })
                    ->first();
    
                if ($matchedLike) {
                    return $matchedLike->is_free ? 0 : (float) $matchedLike->price;
                }
            }
        }
    
        // ✅ Fallback: default price or 0 if marked as free
        return $defaultDelivery->is_free ? 0 : (float) $defaultDelivery->price;
    }
    

}
