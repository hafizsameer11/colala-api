<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevealPhone extends Model
{
    // protected $table = 'reveal_phones';
    protected $fillable = ['chat_id', 'user_id', 'store_id', 'is_revealed'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
