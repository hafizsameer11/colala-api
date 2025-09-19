<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreAddress extends Model
{
    //
     protected $fillable = ['store_id','state','local_government','variant','price','is_free'];
    public function store() { return $this->belongsTo(Store::class); }
}
