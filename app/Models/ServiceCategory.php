<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = ['title','image','is_active'];

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
