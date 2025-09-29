<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralTransfer extends Model
{
    //

    protected $fillable = ['user_id','amount','status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
