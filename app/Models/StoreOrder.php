<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrder extends Model
{
    protected $fillable = [
        'order_id',
        'store_id',
        'status',
        'delivery_pricing_id',
        'shipping_fee',
        'items_subtotal',
        'discount',
        'subtotal_with_shipping',
        'rejection_reason',
        'accepted_at',
        'rejected_at',
        'estimated_delivery_date',
        'delivery_method',
        'delivery_notes',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'estimated_delivery_date' => 'date',
    ];

    // Status constants
    const STATUS_PENDING_ACCEPTANCE = 'pending_acceptance';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';
    const STATUS_PROCESSING = 'processing';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryPricing()
    {
        return $this->belongsTo(StoreDeliveryPricing::class, 'delivery_pricing_id');
    }

    public function orderTracking()
    {
        return $this->hasMany(OrderTracking::class);
    }

    public function chat()
    {
        return $this->hasOne(Chat::class);
    }

    public function escrows()
    {
        return $this->hasManyThrough(Escrow::class, OrderItem::class, 'store_order_id', 'order_item_id');
    }

    // Scopes
    public function scopePendingAcceptance($query)
    {
        return $query->where('status', self::STATUS_PENDING_ACCEPTANCE);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    // Helper methods
    public function isPendingAcceptance()
    {
        return $this->status === self::STATUS_PENDING_ACCEPTANCE;
    }

    public function isAccepted()
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPaid()
    {
        return $this->status === self::STATUS_PAID;
    }

    public function canBeAccepted()
    {
        return $this->status === self::STATUS_PENDING_ACCEPTANCE;
    }

    public function canBeRejected()
    {
        return $this->status === self::STATUS_PENDING_ACCEPTANCE;
    }
}
