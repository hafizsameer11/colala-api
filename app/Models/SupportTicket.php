<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id','category','subject','description','status','order_id','store_order_id'
    ];

    public function user(){ return $this->belongsTo(User::class); }
    public function messages(){ return $this->hasMany(SupportMessage::class,'ticket_id'); }
    public function order(){ return $this->belongsTo(Order::class); }
    public function storeOrder(){ return $this->belongsTo(StoreOrder::class); }
    public function lastMessage()
    {
        return $this->hasOne(SupportMessage::class, 'ticket_id')->latestOfMany();
    }
    public function unreadMessagesCount()
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->where('is_read', false)->count();
    }
}
