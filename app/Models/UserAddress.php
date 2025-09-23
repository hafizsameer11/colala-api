<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    //
    protected $fillable = ['user_id','label','phone','line1','line2','city','state','country','zipcode','is_default'];
    public function user(){ return $this->belongsTo(User::class); }
}
