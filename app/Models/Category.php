<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'title',
        'image',
        'color',
        'parent_id',
    ];

    protected $appends = ['image_url'];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
 return $this->hasMany(Category::class, 'parent_id')
            ->with('children')   // keep nesting
            ->withCount('products'); // include products_count    }
    }
     public function products()
    {
        return $this->hasMany(Product::class, 'category_id'); // << add this
    }

    public function getImageUrlAttribute()
    {
        $storage= $this->image 
            ? Storage::url($this->image)
            : null;
            $url=asset($storage);
        return $url;
    }
    public function stores()
{
    return $this->belongsToMany(Store::class, 'store_categories', 'category_id', 'store_id');
}

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

}

