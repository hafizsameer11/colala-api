<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreReview extends Model
{
    protected $fillable = ['store_id','user_id','rating','comment','images'];
    protected $casts = ['images'=>'array'];
    public function store(){ return $this->belongsTo(Store::class); }
    public function user(){ return $this->belongsTo(User::class); }
}
