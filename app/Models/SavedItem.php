<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'service_id', 'post_id'];

    public function user()    { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class)->with('images'); }
    public function service() { return $this->belongsTo(Service::class); }
    public function post()    { return $this->belongsTo(Post::class); }

    // Dynamic type accessor
    public function getTypeAttribute(): string
    {
        return $this->product_id
            ? 'product'
            : ($this->service_id
                ? 'service'
                : ($this->post_id ? 'post' : 'unknown'));
    }
}
