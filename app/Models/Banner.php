<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['store_id','image_path','link','impressions'];

    public function store() {
        return $this->belongsTo(Store::class);
    }
}