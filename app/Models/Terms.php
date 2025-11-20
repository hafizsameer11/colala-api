<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Terms extends Model
{
    protected $table = 'terms';

    protected $fillable = [
        'buyer_privacy_policy',
        'buyer_terms_and_condition',
        'buyer_return_policy',
        'seller_onboarding_policy',
        'seller_privacy_policy',
        'seller_terms_and_condition',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

