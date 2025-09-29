<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqCategory extends Model
{
     protected $fillable = ['title','video','is_active'];

    public function faqs()
    {
        return $this->hasMany(Faq::class);
    }
}
