<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralWithdrawal extends Model
{
    //
     protected $fillable = ['user_id','amount','bank_name','account_number','account_name','status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
