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
        'remarks',
        'webhook_data'
    ];

    protected $casts = [
        'webhook_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
