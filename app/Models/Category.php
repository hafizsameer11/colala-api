<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'title',
        'image',
        'color'
    ];

    // Make sure the custom attribute is added automatically in response
    protected $appends = ['image_url'];

    /**
     * Accessor for full image URL
     */
    public function getImageUrlAttribute()
    {
        return $this->image 
            ? Storage::url($this->image)  // Generates "/storage/..." URL
            : null;
    }
}
