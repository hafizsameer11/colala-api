<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemPushNotification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'message',
        'link',
        'attachment',
        'audience_type', // 'all', 'buyers', 'sellers', 'specific'
        'target_user_ids', // JSON array of user IDs for specific audience
        'sent_at',
        'status', // 'draft', 'scheduled', 'sent', 'failed'
        'scheduled_for',
        'created_by', // Admin user ID who created the notification
    ];

    protected $casts = [
        'target_user_ids' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notificationRecipients()
    {
        return $this->hasMany(SystemNotificationRecipient::class, 'notification_id');
    }

    // Scopes
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // Helper methods
    public function getAttachmentUrlAttribute()
    {
        return $this->attachment ? asset('storage/' . $this->attachment) : null;
    }

    public function getTotalRecipientsAttribute()
    {
        return $this->notificationRecipients()->count();
    }

    public function getSuccessfulDeliveriesAttribute()
    {
        return $this->notificationRecipients()->where('delivery_status', 'delivered')->count();
    }

    public function getFailedDeliveriesAttribute()
    {
        return $this->notificationRecipients()->where('delivery_status', 'failed')->count();
    }
}
