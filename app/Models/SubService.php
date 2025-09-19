<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubService extends Model
{
     protected $fillable = ['service_id','name','price_from','price_to'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
