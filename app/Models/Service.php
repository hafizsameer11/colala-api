<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
     protected $fillable = [
        'store_id','category_id','name','short_description',
        'full_description','price_from','price_to','discount_price','status'
    ];

    public function media()
    {
        return $this->hasMany(ServiceMedia::class);
    }

    public function subServices()
    {
        return $this->hasMany(SubService::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
