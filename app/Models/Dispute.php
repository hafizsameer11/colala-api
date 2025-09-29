<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    //
     protected $fillable = [
        'chat_id','store_order_id','user_id',
        'category','details','images','status'
    ];

    protected $casts = ['images' => 'array'];

    public function chat()        { return $this->belongsTo(Chat::class); }
    public function storeOrder()  { return $this->belongsTo(StoreOrder::class); }
    public function user()        { return $this->belongsTo(User::class); }
}
