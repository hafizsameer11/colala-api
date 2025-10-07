<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddOnServiceChat extends Model
{
    protected $fillable = [
        'add_on_service_id',
        'sender_id',
        'sender_type',
        'message',
        'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function addOnService()
    {
        return $this->belongsTo(AddOnService::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
