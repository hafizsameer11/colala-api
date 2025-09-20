<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostShare extends Model {
    protected $fillable = ['post_id','user_id','channel'];
    public function post(){ return $this->belongsTo(Post::class); }
}
