<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeChat extends Model
{
    protected $fillable = [
        'dispute_id',
        'buyer_id',
        'seller_id',
        'store_id',
    ];

    // Relationships
    public function dispute()
    {
        return $this->belongsTo(Dispute::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function messages()
    {
        return $this->hasMany(DisputeChatMessage::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(DisputeChatMessage::class)->latestOfMany();
    }
}
