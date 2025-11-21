<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    //

    protected $fillable = [
        'user_id',
        'amount',
        'bank_code',
        'bank_name',
        'account_number',
        'account_name',
        'reference',
        'flutterwave_transfer_id',
        'status',
        'remarks'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
