<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOnboardingStep extends Model
{
    protected $fillable = ['store_id','level','key','status','completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
