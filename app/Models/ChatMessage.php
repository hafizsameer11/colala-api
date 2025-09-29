<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';
     protected $fillable = ['chat_id','sender_id','sender_type','message','image','is_read'];

    public function chat(){ return $this->belongsTo(Chat::class); }
    public function sender(){ return $this->belongsTo(User::class, 'sender_id'); }
    
}
