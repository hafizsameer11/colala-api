<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddOnService extends Model
{
    protected $fillable = [
        'seller_id',
        'name',
        'email',
        'phone',
        'service_type',
        'status',
        'description'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function chats()
    {
        return $this->hasMany(AddOnServiceChat::class);
    }

    public function latestChat()
    {
        return $this->hasOne(AddOnServiceChat::class)->latest();
    }
}
