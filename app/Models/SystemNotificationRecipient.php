<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotificationRecipient extends Model
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'delivery_status', // 'pending', 'delivered', 'failed'
        'delivered_at',
        'failure_reason',
        'device_token',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];

    // Relationships
    public function notification()
    {
        return $this->belongsTo(SystemPushNotification::class, 'notification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeDelivered($query)
    {
        return $query->where('delivery_status', 'delivered');
    }

    public function scopeFailed($query)
    {
        return $query->where('delivery_status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('delivery_status', 'pending');
    }
}
