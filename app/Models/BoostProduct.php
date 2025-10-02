<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoostProduct extends Model
{
    protected $fillable = [
        'product_id',
        'start_date',
        'store_id',
        'status',
        'duration',
        'budget',
        'location',
        'reach',
        'total_amount',
        'impressions',
        'cpc',
        'clicks',
        'payment_method',
        'payment_status',
    ];
    // relations
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
