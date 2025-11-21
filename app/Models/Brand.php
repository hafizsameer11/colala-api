<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
     protected $fillable = ['name','slug','logo','description','status','category_id'];

    protected static function booted()
    {
        static::creating(function ($brand) {
            $brand->slug = Str::slug($brand->name);
        });
        static::updating(function ($brand) {
            $brand->slug = Str::slug($brand->name);
        });
    }

    /**
     * Get the category that owns the brand
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
