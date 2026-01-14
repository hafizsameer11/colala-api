<?php 

// app/Models/Subscription.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'store_id','plan_id','start_date','end_date',
        'status','payment_method','payment_status','transaction_ref',
        'apple_transaction_id','apple_original_transaction_id','apple_receipt_data','is_auto_renewable'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_auto_renewable' => 'boolean',
    ];

    public function plan() {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function store() {
        return $this->belongsTo(Store::class, 'store_id');
    }
}