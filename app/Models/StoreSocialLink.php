<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSocialLink extends Model
{
        protected $fillable = ['store_id','type','url'];
    public function store() { return $this->belongsTo(Store::class); }
}
