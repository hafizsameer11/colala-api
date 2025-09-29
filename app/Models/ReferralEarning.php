<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralEarning extends Model
{
     protected $fillable = ['user_id','total_earned','total_withdrawn','current_balance'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
