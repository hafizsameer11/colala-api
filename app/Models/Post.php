<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id','body','visibility'];
    protected $appends = ['media_urls'];

    public function user(){ return $this->belongsTo(User::class); }
    public function media(){ return $this->hasMany(PostMedia::class)->orderBy('position'); }
    public function likes(){ return $this->hasMany(PostLike::class); }
    public function comments(){ return $this->hasMany(PostComment::class); }
    public function shares(){ return $this->hasMany(PostShare::class); }

    public function getMediaUrlsAttribute(){
        return $this->media->map(fn($m)=>[
            'id'=>$m->id,'type'=>$m->type,'url'=>Storage::url($m->path),'position'=>$m->position
        ]);
    }
}
