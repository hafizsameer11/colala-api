<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $table = 'chats';
      protected $fillable = ['store_order_id','user_id','store_id'];

    public function storeOrder(){ return $this->belongsTo(StoreOrder::class); }
    public function user(){ return $this->belongsTo(User::class); }
    public function store(){ return $this->belongsTo(Store::class); }
    public function messages(){ return $this->hasMany(ChatMessage::class); }

    public function lastMessage() {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }
}
