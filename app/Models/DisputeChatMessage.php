<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeChatMessage extends Model
{
    protected $fillable = [
        'dispute_chat_id',
        'sender_id',
        'sender_type',
        'message',
        'image',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    // Relationships
    public function disputeChat()
    {
        return $this->belongsTo(DisputeChat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
