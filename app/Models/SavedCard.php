<?php 

// app/Models/SavedCard.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedCard extends Model
{
    protected $fillable = [
        'user_id','card_holder','last4','brand',
        'expiry_month','expiry_year','gateway_ref',
        'is_active','is_autodebit'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
